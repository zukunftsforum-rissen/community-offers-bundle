<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;

final class DoorJobService
{
    /**
     * Wie lange ein „dispatched“ Job noch bestätigt werden darf.
     * (muss zum Poll/Confirm-Verhalten eures Pi passen)
     */
    private const int CONFIRM_WINDOW_SECONDS = 30;

    public function __construct(
        private readonly Connection $db,
        private readonly CacheItemPoolInterface $cache,
    ) {}

    public function getConfirmWindowSeconds(): int
    {
        return self::CONFIRM_WINDOW_SECONDS;
    }

    /**
     * Create (or reuse) an "open door" job for member+area.
     *
     * Returns a structured result so controller can map to HTTP.
     *
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
    public function createOpenJob(int $memberId, string $area, string $ip = '', string $userAgent = ''): array
    {
        $now = time();

        // Best effort housekeeping (wichtig: Pull-Modell ohne Worker/Cron)
        $this->expireOldJobs();

        // --- Rate limit: 3/min Member+Area ---
        $limit = 3;
        $windowSeconds = 60;

        $rateKey = sprintf('door_open_m%d_%s', $memberId, $area);
        $rateItem = $this->cache->getItem($rateKey);
        $data = $rateItem->isHit() ? $rateItem->get() : null;

        if (!is_array($data) || !isset($data['count'], $data['resetAt'])) {
            $data = ['count' => 0, 'resetAt' => $now + $windowSeconds];
        }

        if ($now >= (int) $data['resetAt']) {
            $data = ['count' => 0, 'resetAt' => $now + $windowSeconds];
        }

        if ((int) $data['count'] >= $limit) {
            $retryAfterSeconds = max(1, (int) $data['resetAt'] - $now);

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

        // --- Locks: Member+Area + global Area ---
        $lockSeconds = 5;
        $until = $now + $lockSeconds;

        $memberLockKey = sprintf('door_lock_member_m%d_%s', $memberId, $area);
        $memberLock = $this->cache->getItem($memberLockKey);
        if ($memberLock->isHit()) {
            $payload = $memberLock->get();
            $retry = is_array($payload) && isset($payload['until']) ? (int) $payload['until'] - $now : $lockSeconds;

            return [
                'ok' => false,
                'httpStatus' => 429,
                'message' => 'Tür wurde gerade geöffnet.',
                'retryAfterSeconds' => max(1, $retry),
            ];
        }

        $areaLockKey = sprintf('door_lock_area_%s', $area);
        $areaLock = $this->cache->getItem($areaLockKey);
        if ($areaLock->isHit()) {
            $payload = $areaLock->get();
            $retry = is_array($payload) && isset($payload['until']) ? (int) $payload['until'] - $now : $lockSeconds;

            return [
                'ok' => false,
                'httpStatus' => 429,
                'message' => 'Tür ist gerade in Benutzung.',
                'retryAfterSeconds' => max(1, $retry),
            ];
        }

        // --- Idempotenz: aktiven Job wiederverwenden ---
        $dispatchedCutoff = $now - self::CONFIRM_WINDOW_SECONDS;

        $active = $this->db->fetchAssociative(
            "SELECT id, expiresAt, status, dispatchedAt
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
                'dispatchedCutoff' => $dispatchedCutoff,
            ]
        );

        if ($active) {
            $status = (string) $active['status'];

            // expiresAt ist nur für pending relevant.
            // Dispatched läuft über dispatchedAt + CONFIRM_WINDOW.
            $expiresAt = $status === 'pending' ? (int) $active['expiresAt'] : 0;

            return [
                'ok' => true,
                'httpStatus' => 202,
                'message' => 'Job bereits aktiv.',
                'jobId' => (int) $active['id'],
                'status' => $status,
                'expiresAt' => $expiresAt,
            ];
        }

        // --- neuen Job anlegen ---
        $ttlSeconds = 10;
        $expiresAt = $now + $ttlSeconds;

        $userAgent = substr($userAgent, 0, 255);

        $this->db->insert('tl_co_door_job', [
            'tstamp' => $now,
            'createdAt' => $now,
            'expiresAt' => $expiresAt,
            'area' => $area,
            'status' => 'pending',

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

        // Locks setzen wir JETZT, damit nicht mehrere Requests parallel eskalieren.
        $memberLock->set(['until' => $until]);
        $memberLock->expiresAfter($lockSeconds);
        $this->cache->save($memberLock);

        $areaLock->set(['until' => $until]);
        $areaLock->expiresAfter($lockSeconds);
        $this->cache->save($areaLock);

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
        $dispatchedCutoff = $now - self::CONFIRM_WINDOW_SECONDS;

        // pending -> expired (expiresAt)
        $this->db->executeStatement(
            "UPDATE tl_co_door_job
             SET status='expired', tstamp=UNIX_TIMESTAMP(),
                 resultCode=CASE WHEN resultCode='' THEN 'TIMEOUT' ELSE resultCode END,
                 resultMessage=CASE WHEN resultMessage='' THEN 'Pending timeout' ELSE resultMessage END
             WHERE status='pending'
               AND expiresAt > 0
               AND expiresAt < :now",
            ['now' => $now]
        );

        // dispatched -> expired (dispatchedAt + confirm window)
        $this->db->executeStatement(
            "UPDATE tl_co_door_job
             SET status='expired', tstamp=UNIX_TIMESTAMP(),
                 resultCode='TIMEOUT',
                 resultMessage='Confirm timeout'
             WHERE status='dispatched'
               AND dispatchedAt > 0
               AND dispatchedAt < :cutoff",
            ['cutoff' => $dispatchedCutoff]
        );
    }

    /**
     * Claim up to $limit pending jobs for a device/areas and mark them as dispatched.
     *
     * @return array<int, array{id:mixed, area:mixed, nonce:mixed, dispatchedAt:mixed}>
     */
    public function dispatchJobs(string $deviceId, array $areas, int $limit = 3): array
    {
        // Best effort housekeeping
        $this->expireOldJobs();

        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 10) {
            $limit = 10;
        }
        if (!$areas) {
            return [];
        }

        $now = time();

        $this->db->beginTransaction();
        try {
            $ids = $this->db->fetchFirstColumn(
                "SELECT id
                 FROM tl_co_door_job
                 WHERE status='pending'
                   AND area IN (:areas)
                   AND (expiresAt = 0 OR expiresAt >= :now)
                 ORDER BY createdAt ASC
                 LIMIT $limit",
                ['areas' => $areas, 'now' => $now],
                ['areas' => ArrayParameterType::STRING]
            );

            if (!$ids) {
                $this->db->commit();
                return [];
            }

            $claimed = [];
            foreach ($ids as $id) {
                $nonce = bin2hex(random_bytes(16));

                $affected = $this->db->executeStatement(
                    "UPDATE tl_co_door_job
                     SET status='dispatched',
                         dispatchToDeviceId=:deviceId,
                         dispatchedAt=:now,
                         nonce=:nonce,
                         attempts=attempts+1
                     WHERE id=:id
                       AND status='pending'
                       AND (expiresAt = 0 OR expiresAt >= :now)",
                    [
                        'deviceId' => $deviceId,
                        'now' => $now,
                        'nonce' => $nonce,
                        'id' => (int) $id,
                    ]
                );

                if ($affected === 1) {
                    $row = $this->db->fetchAssociative(
                        "SELECT id, area, nonce, dispatchedAt
                         FROM tl_co_door_job
                         WHERE id=:id",
                        ['id' => (int) $id]
                    );
                    if ($row) {
                        $claimed[] = $row;
                    }
                }
            }

            $this->db->commit();
            return $claimed;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Backwards-compatible boolean confirm (true = accepted).
     */
    public function confirmJob(string $deviceId, int $jobId, string $nonce, bool $ok, array $meta = []): bool
    {
        $res = $this->confirmJobResult($deviceId, $jobId, $nonce, $ok, $meta);
        return (bool) ($res['accepted'] ?? false);
    }

    /**
     * Detailed confirm result for API mapping.
     *
     * @return array{
     *   accepted: bool,
     *   httpStatus: int,
     *   status?: string,
     *   error?: string,
     *   message?: string
     * }
     */
    public function confirmJobResult(string $deviceId, int $jobId, string $nonce, bool $ok, array $meta = []): array
    {
        $job = $this->db->fetchAssociative(
            "SELECT id, status, dispatchToDeviceId, nonce, dispatchedAt
             FROM tl_co_door_job
             WHERE id=:id",
            ['id' => $jobId]
        );

        if (!$job) {
            return ['accepted' => false, 'httpStatus' => 404, 'error' => 'not_found'];
        }

        $status = (string) $job['status'];
        $jobDevice = (string) $job['dispatchToDeviceId'];
        $jobNonce = (string) $job['nonce'];

        // idempotent: bereits final -> akzeptiere, wenn device+nonce passen
        if (in_array($status, ['executed', 'failed', 'expired'], true)) {
            $match = ($jobDevice === $deviceId) && hash_equals($jobNonce, $nonce);
            return [
                'accepted' => $match,
                'httpStatus' => $match ? 200 : 403,
                'status' => $status,
                'error' => $match ? null : 'forbidden',
            ];
        }

        if ($status !== 'dispatched') {
            return ['accepted' => false, 'httpStatus' => 409, 'status' => $status, 'error' => 'not_dispatchable'];
        }

        if ($jobDevice !== $deviceId || !hash_equals($jobNonce, $nonce)) {
            return ['accepted' => false, 'httpStatus' => 403, 'status' => $status, 'error' => 'forbidden'];
        }

        $now = time();
        $dispatchedAt = (int) ($job['dispatchedAt'] ?? 0);

        // confirm TTL über dispatchedAt
        if ($dispatchedAt > 0 && $dispatchedAt < ($now - self::CONFIRM_WINDOW_SECONDS)) {
            $this->db->executeStatement(
                "UPDATE tl_co_door_job
                 SET status='expired', tstamp=UNIX_TIMESTAMP(),
                     resultCode='TIMEOUT', resultMessage='Confirm timeout'
                 WHERE id=:id AND status='dispatched'",
                ['id' => $jobId]
            );

            return [
                'accepted' => false,
                'httpStatus' => 410,
                'status' => 'expired',
                'error' => 'confirm_timeout',
            ];
        }

        $finalStatus = $ok ? 'executed' : 'failed';
        $resultCode = $ok ? 'OK' : 'ERR';
        $resultMessage = $ok ? 'Door open executed' : 'Door open failed';

        if ($meta) {
            $suffix = ' | ' . substr(json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 0, 200);
            $resultMessage = substr($resultMessage . $suffix, 0, 255);
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
                'status' => $finalStatus,
                'now' => $now,
                'resultCode' => $resultCode,
                'resultMessage' => $resultMessage,
                'id' => $jobId,
                'deviceId' => $deviceId,
                'nonce' => $nonce,
            ]
        );

        return [
            'accepted' => ($affected === 1),
            'httpStatus' => ($affected === 1) ? 200 : 409,
            'status' => $finalStatus,
            'error' => ($affected === 1) ? null : 'not_dispatchable',
        ];
    }
}
