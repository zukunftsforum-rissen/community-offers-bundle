<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Workflow\DoorJob;

final class DoorJobService
{
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    private const RATE_LIMIT_MAX_ATTEMPTS = 3;

    private const LOCK_SECONDS = 5;

    public function __construct(
        private readonly Connection $db,
        private readonly CacheItemPoolInterface $cache,
        private readonly WorkflowInterface $doorJobStateMachine,
        private readonly LoggingService $logging,
        private readonly DoorAuditLogger $audit,
        private readonly int $confirmWindowSeconds,
    ) {
    }

    public function getConfirmWindowSeconds(): int
    {
        return $this->confirmWindowSeconds;
    }

    /**
     * @return array{
     *   ok: bool,
     *   httpStatus: int,
     *   message: string,
     *   jobId?: int,
     *   status?: string,
     *   expiresAt?: int,
     *   retryAfterSeconds?: int
     * }
     */
    public function createOpenJob(int $memberId, string $area, string $ip = '', string $userAgent = '', string $correlationId = ''): array
    {
        $now = time();

        $this->db->executeStatement(
            "UPDATE tl_co_door_job
             SET status='expired'
             WHERE status = 'pending'
               AND expiresAt > 0
               AND expiresAt < :now",
            ['now' => $now],
        );

        $rateLimitMaxAttempts = self::RATE_LIMIT_MAX_ATTEMPTS;
        $rateLimitWindowSeconds = self::RATE_LIMIT_WINDOW_SECONDS;

        $rateKey = \sprintf('door_open_m%d_%s', $memberId, $area);
        $rateItem = $this->cache->getItem($rateKey);
        $data = $rateItem->isHit() ? $rateItem->get() : null;

        if (!\is_array($data) || !isset($data['count'], $data['resetAt'])) {
            $data = ['count' => 0, 'resetAt' => $now + $rateLimitWindowSeconds];
        }

        if ($now >= (int) $data['resetAt']) {
            $data = ['count' => 0, 'resetAt' => $now + $rateLimitWindowSeconds];
        }

        if ((int) $data['count'] >= $rateLimitMaxAttempts) {
            $retryAfterSeconds = max(1, (int) $data['resetAt'] - $now);

            $this->logging->warning('door_job.rate_rateLimitMaxAttemptsed', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'retryAfterSeconds' => $retryAfterSeconds,
            ]);

            $this->audit->audit(
                action: 'door_open',
                area: $area,
                result: 'rate_limited',
                message: 'Door open rate limited',
                context: ['retryAfterSeconds' => $retryAfterSeconds],
                correlationId: $correlationId,
                memberId: $memberId,
            );

            return [
                'ok' => false,
                'httpStatus' => 429,
                'message' => 'Zu viele Versuche – bitte kurz warten.',
                'retryAfterSeconds' => $retryAfterSeconds,
            ];
        }

        $data['count'] = (int) $data['count'] + 1;
        $rateItem->set($data);
        $rateItem->expiresAfter(max(1, (int) $data['resetAt'] - $now));
        $this->cache->save($rateItem);

        $lockSeconds = self::LOCK_SECONDS;
        $until = $now + $lockSeconds;

        $memberLockKey = \sprintf('door_lock_member_m%d_%s', $memberId, $area);
        $memberLock = $this->cache->getItem($memberLockKey);
        if ($memberLock->isHit()) {
            $payload = $memberLock->get();
            $retry = \is_array($payload) && isset($payload['until']) ? (int) $payload['until'] - $now : $lockSeconds;

            $this->logging->warning('door_job.member_locked', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'retryAfterSeconds' => max(1, $retry),
            ]);

            return [
                'ok' => false,
                'httpStatus' => 429,
                'message' => 'Tür wurde gerade geöffnet.',
                'retryAfterSeconds' => max(1, $retry),
            ];
        }

        $areaLockKey = \sprintf('door_lock_area_%s', $area);
        $areaLock = $this->cache->getItem($areaLockKey);
        if ($areaLock->isHit()) {
            $payload = $areaLock->get();
            $retry = \is_array($payload) && isset($payload['until']) ? (int) $payload['until'] - $now : $lockSeconds;

            $this->logging->warning('door_job.area_locked', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'retryAfterSeconds' => max(1, $retry),
            ]);

            return [
                'ok' => false,
                'httpStatus' => 429,
                'message' => 'Tür ist gerade in Benutzung.',
                'retryAfterSeconds' => max(1, $retry),
            ];
        }

        $active = $this->db->fetchAssociative(
            "SELECT id, expiresAt, status, correlationId
             FROM tl_co_door_job
             WHERE requestedByMemberId = :memberId
               AND area = :area
               AND (
                    (status = 'pending' AND (expiresAt = 0 OR expiresAt >= :now))
                 OR (status = 'dispatched' AND dispatchedAt >= :dispatchedCutoff)
               )
             ORDER BY createdAt DESC
             LIMIT 1",
            [
                'memberId' => $memberId,
                'area' => $area,
                'now' => $now,
                'dispatchedCutoff' => $now - $this->confirmWindowSeconds,
            ],
        );

        if ($active) {
            $status = (string) $active['status'];
            $expiresAt = 'pending' === $status ? (int) $active['expiresAt'] : 0;
            $existingCid = (string) ($active['correlationId'] ?? '');

            $this->logging->info('door_job.reused', [
                'cid' => $existingCid ?: $correlationId,
                'jobId' => (int) $active['id'],
                'memberId' => $memberId,
                'area' => $area,
                'status' => $status,
            ]);

            return [
                'ok' => true,
                'httpStatus' => 202,
                'message' => 'Job bereits aktiv.',
                'jobId' => (int) $active['id'],
                'status' => $status,
                'expiresAt' => $expiresAt,
            ];
        }

        $ttlSeconds = max(120, $this->confirmWindowSeconds);
        $expiresAt = $now + $ttlSeconds;

        $userAgent = substr($userAgent, 0, 255);

        if ('' === $correlationId) {
            $correlationId = $this->generateCorrelationId();
        }

        $this->db->insert('tl_co_door_job', [
            'tstamp' => $now,
            'createdAt' => $now,
            'expiresAt' => $expiresAt,
            'area' => $area,
            'status' => 'pending',
            'correlationId' => substr($correlationId, 0, 64),

            'requestedByMemberId' => $memberId,
            'requestIp' => $ip,
            'userAgent' => $userAgent,

            'dispatchToDeviceId' => '',
            'dispatchedAt' => 0,
            'executedAt' => 0,
            'nonce' => '',
            'attempts' => 0,

            'resultCode' => '',
            'resultMessage' => '',
        ]);

        $jobId = (int) $this->db->lastInsertId();

        $memberLock->set(['until' => $until]);
        $memberLock->expiresAfter($lockSeconds);
        $this->cache->save($memberLock);

        $areaLock->set(['until' => $until]);
        $areaLock->expiresAfter($lockSeconds);
        $this->cache->save($areaLock);

        $this->logging->info('door_job.created', [
            'cid' => $correlationId,
            'jobId' => $jobId,
            'memberId' => $memberId,
            'area' => $area,
            'expiresAt' => $expiresAt,
        ]);

        $this->audit->audit(
            action: 'door_open',
            area: $area,
            result: 'granted',
            message: 'Door job created',
            context: ['jobId' => $jobId, 'expiresAt' => $expiresAt],
            correlationId: $correlationId,
            memberId: $memberId,
        );

        return [
            'ok' => true,
            'httpStatus' => 202,
            'message' => 'Job angenommen.',
            'jobId' => $jobId,
            'status' => 'pending',
            'expiresAt' => $expiresAt,
        ];
    }

    public function expireOldJobs(): void
    {
        $now = time();
        $dispatchedCutoff = $now - $this->confirmWindowSeconds;

        $this->db->executeStatement(
            "UPDATE tl_co_door_job
             SET status='expired', tstamp=UNIX_TIMESTAMP()
             WHERE status ='pending'
               AND expiresAt > 0
               AND expiresAt < :now",
            ['now' => $now],
        );

        $this->db->executeStatement(
            "UPDATE tl_co_door_job
             SET status='expired', tstamp=UNIX_TIMESTAMP(), resultCode='TIMEOUT', resultMessage='Confirm timeout'
             WHERE status ='dispatched'
               AND dispatchedAt > 0
               AND dispatchedAt < :cutoff",
            ['cutoff' => $dispatchedCutoff],
        );
    }

    /**
     * @param list<string> $areas
     *
     * @return list<array{jobId:int, area:string, nonce:string, expiresInMs:int, correlationId:string}>
     */
    public function dispatchJobs(string $deviceId, array $areas, int $rateLimitMaxAttempts = 3): array
    {
        if ($rateLimitMaxAttempts < 1) {
            $rateLimitMaxAttempts = 1;
        }
        if ($rateLimitMaxAttempts > 10) {
            $rateLimitMaxAttempts = 10;
        }
        if (!$areas) {
            return [];
        }

        $now = time();

        $this->db->beginTransaction();

        try {
            $rows = $this->db->fetchAllAssociative(
                "SELECT id, area, status, correlationId, requestedByMemberId
                 FROM tl_co_door_job
                 WHERE status='pending'
                   AND area IN (:areas)
                   AND (expiresAt >= :now)
                 ORDER BY createdAt ASC
                 LIMIT $rateLimitMaxAttempts",
                ['areas' => $areas, 'now' => $now],
                ['areas' => ArrayParameterType::STRING],
            );

            if (!$rows) {
                $this->db->commit();

                return [];
            }

            $claimed = [];

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $area = (string) ($row['area'] ?? '');
                $status = (string) ($row['status'] ?? '');
                $memberId = (int) ($row['requestedByMemberId'] ?? 0);

                if ($id < 1) {
                    continue;
                }

                $job = new DoorJob($id, $area, $status);
                $job->setDeviceId($deviceId);

                $dtNow = (new \DateTimeImmutable())->setTimestamp($now);
                $job->setDispatchedAt($dtNow);
                $job->setConfirmExpiresAt($dtNow->modify('+'.$this->confirmWindowSeconds.' seconds'));

                if (!$this->doorJobStateMachine->can($job, 'dispatch')) {
                    continue;
                }

                $nonce = bin2hex(random_bytes(32));

                $this->doorJobStateMachine->apply($job, 'dispatch');

                $affected = $this->db->executeStatement(
                    "UPDATE tl_co_door_job
                     SET status=:status,
                         dispatchToDeviceId=:deviceId,
                         dispatchedAt=:now,
                         nonce=:nonce,
                         attempts=attempts+1
                     WHERE id=:id
                       AND status='pending'
                       AND (expiresAt = 0 OR expiresAt >= :now)",
                    [
                        'status' => $job->getStatus(),
                        'deviceId' => $deviceId,
                        'now' => $now,
                        'nonce' => $nonce,
                        'id' => $id,
                    ],
                );

                if (1 === $affected) {
                    $row2 = $this->db->fetchAssociative(
                        'SELECT id, area, nonce, expiresAt, correlationId
                         FROM tl_co_door_job
                         WHERE id=:id',
                        ['id' => $id],
                    );

                    if ($row2) {
                        $expiresAt = (int) ($row2['expiresAt'] ?? 0);
                        $jobCid = (string) ($row2['correlationId'] ?? '');

                        $claimed[] = [
                            'jobId' => (int) ($row2['id'] ?? 0),
                            'area' => (string) ($row2['area'] ?? ''),
                            'nonce' => (string) ($row2['nonce'] ?? ''),
                            'expiresInMs' => $expiresAt > 0 ? max(0, ($expiresAt - $now) * 1000) : 0,
                            'correlationId' => $jobCid,
                        ];

                        $this->logging->info('door_dispatch.dispatched', [
                            'cid' => $jobCid,
                            'jobId' => (int) $row2['id'],
                            'deviceId' => $deviceId,
                            'area' => (string) $row2['area'],
                        ]);

                        $this->audit->audit(
                            action: 'door_dispatch',
                            area: (string) $row2['area'],
                            result: 'dispatched',
                            message: 'Door job dispatched to device',
                            context: ['jobId' => (int) $row2['id'], 'deviceId' => $deviceId],
                            correlationId: $jobCid,
                            memberId: $memberId,
                        );
                    }
                }
            }

            $this->db->commit();

            return $claimed;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logging->error('door_job.dispatch_failed', [
                'deviceId' => $deviceId,
                'areas' => $areas,
                'exceptionClass' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $deviceRow
     *
     * @return array{jobId:int, area:string, nonce:string, expiresInMs:int, correlationId:string}|null
     */
    public function dispatchNextJobForDevice(array $deviceRow): array|null
    {
        $deviceId = (string) ($deviceRow['deviceId'] ?? '');
        $areas = $deviceRow['areas'] ?? null;

        if ('' === $deviceId) {
            return null;
        }

        $deviceAreas = $this->deserializeAreas($areas);

        if ([] === $deviceAreas) {
            return null;
        }

        $jobs = $this->dispatchJobs($deviceId, $deviceAreas, 1);

        if ([] === $jobs) {
            return null;
        }

        return $jobs[0];
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function confirmJob(string $deviceId, int $jobId, string $nonce, bool $ok, array $meta = []): bool
    {
        $res = $this->confirmJobDetailed($deviceId, $jobId, $nonce, $ok, $meta);

        return (bool) $res['accepted'];
    }

    /**
     * @param array<string, mixed> $meta
     *
     * @return array{accepted: bool, httpStatus: int, status?: string, error?: string, message?: string, resultCode?: string, resultMessage?: string}
     */
    public function confirmJobDetailed(string $deviceId, int $jobId, string $nonce, bool $ok, array $meta = []): array
    {
        $job = $this->db->fetchAssociative(
            'SELECT id, status, area, dispatchToDeviceId, nonce, dispatchedAt, requestedByMemberId, correlationId
             FROM tl_co_door_job
             WHERE id=:id',
            ['id' => $jobId],
        );

        if (!$job) {
            $this->logging->warning('door_job.confirm_not_found', [
                'jobId' => $jobId,
                'deviceId' => $deviceId,
            ]);

            return ['accepted' => false, 'httpStatus' => 404, 'error' => 'not_found'];
        }

        $status = (string) ($job['status'] ?? '');
        $area = (string) ($job['area'] ?? '');
        $jobDevice = (string) ($job['dispatchToDeviceId'] ?? '');
        $jobNonce = (string) ($job['nonce'] ?? '');
        $memberId = (int) ($job['requestedByMemberId'] ?? 0);
        $jobCorrelationId = (string) ($job['correlationId'] ?? '');

        $this->logging->info('door_confirm.attempt', [
            'cid' => $jobCorrelationId,
            'jobId' => $jobId,
            'deviceId' => $deviceId,
            'area' => $area,
            'ok' => $ok,
        ]);

        $this->audit->audit(
            action: 'door_confirm',
            area: $area,
            result: 'attempt',
            message: 'Device confirm received',
            context: ['jobId' => $jobId, 'deviceId' => $deviceId, 'ok' => $ok],
            correlationId: $jobCorrelationId,
            memberId: $memberId,
        );

        if (\in_array($status, ['executed', 'failed'], true)) {
            if ($jobDevice === $deviceId && '' !== $jobNonce && hash_equals($jobNonce, $nonce)) {
                $this->logging->info('door_job.confirm_idempotent', [
                    'cid' => $jobCorrelationId,
                    'jobId' => $jobId,
                    'deviceId' => $deviceId,
                    'status' => $status,
                ]);

                return ['accepted' => true, 'httpStatus' => 200, 'status' => $status];
            }

            $this->logging->warning('door_job.confirm_forbidden_final_state', [
                'cid' => $jobCorrelationId,
                'jobId' => $jobId,
                'deviceId' => $deviceId,
                'status' => $status,
            ]);

            return ['accepted' => false, 'httpStatus' => 403, 'error' => 'forbidden', 'status' => $status];
        }

        if ('expired' === $status) {
            if ($jobDevice === $deviceId && '' !== $jobNonce && hash_equals($jobNonce, $nonce)) {
                $this->logging->warning('door_job.confirm_expired', [
                    'cid' => $jobCorrelationId,
                    'jobId' => $jobId,
                    'deviceId' => $deviceId,
                ]);

                return ['accepted' => false, 'httpStatus' => 410, 'error' => 'confirm_timeout', 'status' => 'expired'];
            }

            $this->logging->warning('door_job.confirm_forbidden_final_state', [
                'cid' => $jobCorrelationId,
                'jobId' => $jobId,
                'deviceId' => $deviceId,
                'status' => $status,
            ]);

            return ['accepted' => false, 'httpStatus' => 403, 'error' => 'forbidden', 'status' => $status];
        }
        if ('dispatched' !== $status) {
            $this->logging->warning('door_job.confirm_invalid_state', [
                'cid' => $jobCorrelationId,
                'jobId' => $jobId,
                'status' => $status,
                'deviceId' => $deviceId,
            ]);

            return ['accepted' => false, 'httpStatus' => 409, 'error' => 'not_dispatchable', 'status' => $status];
        }

        if ($jobDevice !== $deviceId) {
            $this->logging->warning('door_job.confirm_wrong_device', [
                'cid' => $jobCorrelationId,
                'jobId' => $jobId,
                'deviceId' => $deviceId,
                'expectedDeviceId' => $jobDevice,
            ]);

            return ['accepted' => false, 'httpStatus' => 403, 'error' => 'forbidden', 'status' => $status];
        }

        if ('' === $jobNonce || !hash_equals($jobNonce, $nonce)) {
            $this->logging->warning('door_job.confirm_wrong_nonce', [
                'cid' => $jobCorrelationId,
                'jobId' => $jobId,
                'deviceId' => $deviceId,
            ]);

            $this->audit->audit(
                action: 'door_confirm',
                area: $area,
                result: 'forbidden',
                message: 'Door confirm rejected due to wrong nonce',
                context: ['jobId' => $jobId, 'deviceId' => $deviceId],
                correlationId: $jobCorrelationId,
                memberId: $memberId,
            );

            return ['accepted' => false, 'httpStatus' => 403, 'error' => 'forbidden', 'status' => $status];
        }

        $now = time();
        $dispatchedAt = (int) ($job['dispatchedAt'] ?? 0);

        if ($dispatchedAt > 0 && $dispatchedAt < $now - $this->confirmWindowSeconds) {
            $doorJob = new DoorJob(
                (int) $job['id'],
                '',
                $status,
                (new \DateTimeImmutable())->setTimestamp($dispatchedAt),
                (new \DateTimeImmutable())->setTimestamp($dispatchedAt)->modify('+'.$this->confirmWindowSeconds.' seconds'),
            );

            if ($this->doorJobStateMachine->can($doorJob, 'expire_dispatched')) {
                $this->doorJobStateMachine->apply($doorJob, 'expire_dispatched');
            }

            $affected = $this->db->executeStatement(
                "UPDATE tl_co_door_job
     SET status='expired',
         tstamp=UNIX_TIMESTAMP(),
         resultCode='TIMEOUT',
         resultMessage='Confirm timeout'
     WHERE id=:id
       AND status='dispatched'
       AND dispatchToDeviceId=:deviceId
       AND nonce=:nonce",
                [
                    'id' => $jobId,
                    'deviceId' => $deviceId,
                    'nonce' => $nonce,
                ],
            );

            if (1 !== $affected) {
                $freshJob = $this->db->fetchAssociative(
                    'SELECT status
         FROM tl_co_door_job
         WHERE id=:id',
                    ['id' => $jobId],
                );

                $freshStatus = (string) ($freshJob['status'] ?? 'expired');

                $this->logging->warning('door_job.confirm_timeout_conflict', [
                    'cid' => $jobCorrelationId,
                    'jobId' => $jobId,
                    'deviceId' => $deviceId,
                    'freshStatus' => $freshStatus,
                ]);

                return match ($freshStatus) {
                    'expired' => [
                        'accepted' => false,
                        'httpStatus' => 410,
                        'error' => 'confirm_timeout',
                        'status' => 'expired',
                    ],
                    'executed', 'failed' => [
                        'accepted' => true,
                        'httpStatus' => 200,
                        'status' => $freshStatus,
                    ],
                    default => [
                        'accepted' => false,
                        'httpStatus' => 409,
                        'error' => 'not_dispatchable',
                        'status' => $freshStatus,
                    ],
                };
            }

            $this->audit->audit(
                action: 'door_confirm',
                area: $area,
                result: 'timeout',
                message: 'Confirm timeout',
                context: ['jobId' => $jobId, 'deviceId' => $deviceId],
                correlationId: $jobCorrelationId,
                memberId: $memberId,
            );

            $this->logging->warning('door_job.confirm_timeout', [
                'cid' => $jobCorrelationId,
                'jobId' => $jobId,
                'deviceId' => $deviceId,
            ]);

            return ['accepted' => false, 'httpStatus' => 410, 'error' => 'confirm_timeout', 'status' => 'expired'];
        }

        $doorJob = new DoorJob(
            (int) $job['id'],
            '',
            $status,
            (new \DateTimeImmutable())->setTimestamp($dispatchedAt),
            (new \DateTimeImmutable())->setTimestamp($dispatchedAt)->modify('+'.$this->confirmWindowSeconds.' seconds'),
        );

        $transition = $ok ? 'execute' : 'fail';

        if (!$this->doorJobStateMachine->can($doorJob, $transition)) {
            $this->logging->warning('door_job.confirm_transition_blocked', [
                'cid' => $jobCorrelationId,
                'jobId' => $jobId,
                'transition' => $transition,
                'status' => $status,
            ]);

            return ['accepted' => false, 'httpStatus' => 409, 'error' => 'not_dispatchable', 'status' => $status];
        }

        $this->doorJobStateMachine->apply($doorJob, $transition);

        $newStatus = $doorJob->getStatus();
        $resultCode = $ok ? 'OK' : 'ERR';
        $resultMessage = $ok ? 'Door open executed' : 'Door open failed';

        if ($meta) {
            $suffix = ' | '.substr((string) (json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), 0, 200);
            $resultMessage = substr($resultMessage.$suffix, 0, 255);
        }

        $affected = $this->db->executeStatement(
            "UPDATE tl_co_door_job
     SET status=:status,
         executedAt=:now,
         resultCode=:resultCode,
         resultMessage=:resultMessage
     WHERE id=:id
       AND status='dispatched'
       AND dispatchToDeviceId=:deviceId
       AND nonce=:nonce",
            [
                'status' => $newStatus,
                'now' => $now,
                'resultCode' => $resultCode,
                'resultMessage' => $resultMessage,
                'id' => $jobId,
                'deviceId' => $deviceId,
                'nonce' => $nonce,
            ],
        );

        if (1 !== $affected) {
            $freshJob = $this->db->fetchAssociative(
                'SELECT status, correlationId
         FROM tl_co_door_job
         WHERE id=:id',
                ['id' => $jobId],
            );

            $freshStatus = (string) ($freshJob['status'] ?? $status);

            $this->logging->warning('door_job.confirm_update_conflict', [
                'cid' => $jobCorrelationId,
                'jobId' => $jobId,
                'deviceId' => $deviceId,
                'expectedTransition' => $transition,
                'freshStatus' => $freshStatus,
            ]);

            return match ($freshStatus) {
                'expired' => [
                    'accepted' => false,
                    'httpStatus' => 410,
                    'error' => 'confirm_timeout',
                    'status' => 'expired',
                ],
                'executed', 'failed' => [
                    'accepted' => true,
                    'httpStatus' => 200,
                    'status' => $freshStatus,
                ],
                default => [
                    'accepted' => false,
                    'httpStatus' => 409,
                    'error' => 'not_dispatchable',
                    'status' => $freshStatus,
                ],
            };
        }

        $this->audit->audit(
            action: 'door_confirm',
            area: $area,
            result: $ok ? 'confirmed' : 'failed',
            message: $ok ? 'Door execution confirmed' : 'Door execution failed',
            context: [
                'jobId' => $jobId,
                'deviceId' => $deviceId,
                'status' => $newStatus,
                'meta' => $meta,
            ],
            correlationId: $jobCorrelationId,
            memberId: $memberId,
        );

        $this->logging->info('door_confirm.confirmed', [
            'cid' => $jobCorrelationId,
            'jobId' => $jobId,
            'deviceId' => $deviceId,
            'status' => $newStatus,
            'ok' => $ok,
        ]);

        return ['accepted' => true, 'httpStatus' => 200, 'status' => $newStatus];
    }

    /**
     * @return array{
     *   ok: bool,
     *   httpStatus: int,
     *   message: string,
     *   jobId?: int,
     *   status?: string,
     *   area?: string,
     *   expiresAt?: int
     * }
     */
    public function getJobStatus(int $jobId, int $memberId): array
    {
        $job = $this->db->fetchAssociative(
            'SELECT id, area, status, expiresAt, requestedByMemberId
         FROM tl_co_door_job
         WHERE id = :jobId
         LIMIT 1',
            ['jobId' => $jobId],
        );

        if (!$job) {
            return [
                'ok' => false,
                'httpStatus' => 404,
                'message' => 'Job nicht gefunden.',
            ];
        }

        if ((int) $job['requestedByMemberId'] !== $memberId) {
            return [
                'ok' => false,
                'httpStatus' => 403,
                'message' => 'Kein Zugriff auf diesen Job.',
            ];
        }

        return [
            'ok' => true,
            'httpStatus' => 200,
            'message' => 'OK',
            'jobId' => (int) $job['id'],
            'status' => (string) $job['status'],
            'area' => (string) $job['area'],
            'expiresAt' => (int) $job['expiresAt'],
        ];
    }

    /**
     * @return list<string>
     */
    private function deserializeAreas(mixed $areas): array
    {
        if (\is_array($areas)) {
            return array_values(array_filter(array_map('strval', $areas)));
        }

        $deserialized = StringUtil::deserialize($areas, true);

        return array_values(array_filter(array_map('strval', $deserialized)));
    }

    private function generateCorrelationId(): string
    {
        return str_pad(dechex(time()), 8, '0', STR_PAD_LEFT)
            .bin2hex(random_bytes(28));
    }
}
