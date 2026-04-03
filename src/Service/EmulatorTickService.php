<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Doctrine\DBAL\Connection;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Service\DeviceHeartbeatService;

final class EmulatorTickService
{
    public function __construct(
        private readonly Connection $db,
        private readonly DoorJobService $doorJobService,
        private readonly DeviceHeartbeatService $heartbeatService,
        private readonly DoorAuditLogger $audit,
        private readonly LoggingService $logging,
        private readonly string $mode,
    ) {
    }

    public function runTick(): int
    {
        if ('emulator' !== $this->mode) {
            return 0;
        }

        $processed = 0;

        $devices = $this->db->fetchAllAssociative(
            "SELECT id, deviceId, name, areas, isEmulator, enabled
             FROM tl_co_device
             WHERE enabled = 1
               AND isEmulator = 1
             ORDER BY id ASC"
        );

        foreach ($devices as $device) {
            $deviceId = (string) ($device['deviceId'] ?? '');

            if ('' === $deviceId) {
                continue;
            }

            try {
                $this->heartbeatService->registerPoll($deviceId);

                $job = $this->doorJobService->dispatchNextJobForDevice($device);

                if (null === $job) {
                    continue;
                }

                $jobId = (int) ($job['jobId'] ?? 0);
                $nonce = (string) ($job['nonce'] ?? '');
                $area = (string) ($job['area'] ?? '');
                $correlationId = (string) ($job['correlationId'] ?? '');

                if ($jobId < 1 || '' === $nonce) {
                    $this->logging->warning('door_emulator.invalid_job_payload', [
                        'deviceId' => $deviceId,
                        'job' => $job,
                    ]);

                    continue;
                }

                $this->logging->info('door_emulator.execute', [
                    'cid' => $correlationId,
                    'jobId' => $jobId,
                    'deviceId' => $deviceId,
                    'area' => $area,
                ]);

                $this->audit->audit(
                    action: 'door_confirm',
                    area: $area,
                    result: 'attempt',
                    message: 'Emulator cron confirming dispatched job',
                    context: [
                        'jobId' => $jobId,
                        'deviceId' => $deviceId,
                        'source' => 'emulator_cron',
                    ],
                    correlationId: $correlationId !== '' ? $correlationId : null,
                );

                $result = $this->doorJobService->confirmJobDetailed(
                    $deviceId,
                    $jobId,
                    $nonce,
                    true,
                    [
                        'source' => 'emulator_cron',
                        'result' => 'emulator_ok',
                    ],
                );

                if (($result['accepted'] ?? false) === true) {
                    ++$processed;
                } else {
                    $this->logging->warning('door_emulator.confirm_rejected', [
                        'cid' => $correlationId,
                        'jobId' => $jobId,
                        'deviceId' => $deviceId,
                        'httpStatus' => $result['httpStatus'] ?? null,
                        'error' => $result['error'] ?? null,
                        'status' => $result['status'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logging->error('door_emulator.tick_failed', [
                    'deviceId' => $deviceId,
                    'exceptionClass' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }
}
