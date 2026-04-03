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

        /** @var list<array{id:mixed, deviceId:mixed, name:mixed, areas:mixed, isEmulator:mixed, enabled:mixed}> $devices */
        $devices = $this->db->fetchAllAssociative(
            'SELECT id, deviceId, name, areas, isEmulator, enabled
             FROM tl_co_device
             WHERE enabled = 1
               AND isEmulator = 1
             ORDER BY id ASC',
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

                $jobId = $job['jobId'];
                $nonce = $job['nonce'];
                $area = $job['area'];
                $correlationId = $job['correlationId'];

                $this->logging->info('door_emulator.execute', [
                    'cid' => $correlationId,
                    'jobId' => $jobId,
                    'deviceId' => $deviceId,
                    'area' => $area,
                ]);

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

                if ($result['accepted']) {
                    ++$processed;

                    continue;
                }

                $warningContext = [
                    'cid' => $correlationId,
                    'jobId' => $jobId,
                    'deviceId' => $deviceId,
                ];

                $warningContext['httpStatus'] = $result['httpStatus'];

                if (\array_key_exists('error', $result)) {
                    $warningContext['error'] = $result['error'];
                }

                if (\array_key_exists('status', $result)) {
                    $warningContext['status'] = $result['status'];
                }

                $this->logging->warning('door_emulator.confirm_rejected', $warningContext);
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
