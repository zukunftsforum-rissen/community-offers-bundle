<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Device\Simulator;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;

final class SimulatorDeviceService
{
    public const SIMULATOR_DEVICE_ID = 'shed-simulator';

    public const SIMULATOR_DISPLAY_NAME = 'Shed Simulator';

    private const DEFAULT_AREAS = ['sharing', 'workshop', 'depot', 'swap-house'];

    public function __construct(
        private readonly Connection $connection,
        private readonly DoorJobService $doorJobService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function poll(string $deviceId = self::SIMULATOR_DEVICE_ID): array
    {
        $device = $this->findOrCreateSimulator($deviceId);

        $databaseId = (string) ($device['id'] ?? '');
        if ('' === $databaseId) {
            throw new NotFoundHttpException('Simulator device id missing.');
        }

        $areas = $this->extractAreas($device);
        $jobs = $this->doorJobService->dispatchJobs($databaseId, $areas, 1);

        return [
            'jobs' => array_map(static fn (array $job): array => [
                'jobId' => $job['jobId'],
                'area' => $job['area'],
                'nonce' => $job['nonce'],
                'expiresInMs' => $job['expiresInMs'],
                'correlationId' => $job['correlationId'],
            ], $jobs),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function confirm(array $payload, string $deviceId = self::SIMULATOR_DEVICE_ID): array
    {
        $device = $this->findOrCreateSimulator($deviceId);

        $databaseId = (string) ($device['id'] ?? '');
        if ('' === $databaseId) {
            throw new NotFoundHttpException('Simulator device id missing.');
        }

        $jobId = (int) ($payload['jobId'] ?? 0);
        $nonce = trim((string) ($payload['nonce'] ?? ''));
        $ok = (bool) ($payload['ok'] ?? true);
        $meta = \is_array($payload['meta'] ?? null) ? $payload['meta'] : ['source' => 'door-simulator'];

        if ($jobId <= 0 || '' === $nonce) {
            throw new BadRequestHttpException('jobId and nonce are required.');
        }

        $result = $this->doorJobService->confirmJobDetailed(
            $databaseId,
            $jobId,
            $nonce,
            $ok,
            $meta,
        );

        return [
            'ok' => true,
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrCreateSimulator(string $deviceId = self::SIMULATOR_DEVICE_ID): array
    {
        $device = $this->findSimulator($deviceId);

        if (null === $device) {
            $this->createSimulator($deviceId);
            $device = $this->findSimulator($deviceId);
        }

        if (null === $device) {
            throw new \RuntimeException('Failed to create simulator device.');
        }

        return $this->ensureSimulatorConfiguration($device);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSimulator(string $deviceId = self::SIMULATOR_DEVICE_ID): array|null
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM tl_co_device WHERE deviceId = ? LIMIT 1',
            [$deviceId],
        );

        return $row ?: null;
    }

    public function createSimulator(string $deviceId = self::SIMULATOR_DEVICE_ID): void
    {
        $now = time();

        $this->connection->insert('tl_co_device', [
            'tstamp' => $now,
            'name' => self::SIMULATOR_DISPLAY_NAME,
            'deviceId' => $deviceId,
            'isSimulator' => 1,
            'enabled' => 1,
            'areas' => serialize(self::DEFAULT_AREAS),
            'apiTokenHash' => '',
            'lastSeen' => 0,
            'ipLast' => '',
        ]);
    }

    /**
     * @param array<string, mixed> $device
     *
     * @return array<string, mixed>
     */
    private function ensureSimulatorConfiguration(array $device): array
    {
        $updates = [];

        if (1 !== (int) ($device['isSimulator'] ?? 0)) {
            $updates['isSimulator'] = 1;
        }

        if (1 !== (int) ($device['enabled'] ?? 0)) {
            $updates['enabled'] = 1;
        }

        $areas = $this->extractAreas($device);
        $missingAreas = array_diff(self::DEFAULT_AREAS, $areas);

        if ([] !== $missingAreas) {
            $updates['areas'] = serialize(array_values(array_unique([...$areas, ...self::DEFAULT_AREAS])));
        }

        if ([] !== $updates) {
            $updates['tstamp'] = time();

            $this->connection->update(
                'tl_co_device',
                $updates,
                ['id' => $device['id']],
            );

            $refetched = $this->findSimulator((string) $device['deviceId']);

            if (null !== $refetched) {
                return $refetched;
            }
        }

        return $device;
    }

    /**
     * @param array<string, mixed> $device
     *
     * @return list<string>
     */
    private function extractAreas(array $device): array
    {
        $areasRaw = $device['areas'] ?? '';

        if (!\is_string($areasRaw) || '' === $areasRaw) {
            return [];
        }

        $areas = @unserialize($areasRaw);

        if (\is_array($areas)) {
            return array_values(array_filter(array_map('strval', $areas)));
        }

        return [];
    }
}
