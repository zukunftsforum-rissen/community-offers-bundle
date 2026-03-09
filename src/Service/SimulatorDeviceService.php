<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;

final class SimulatorDeviceService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DoorJobService $doorJobService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function poll(string $deviceName = 'shed-simulator'): array
    {
        $device = $this->connection->fetchAssociative(
            'SELECT * FROM tl_co_device WHERE name = ? AND enabled = ? LIMIT 1',
            [$deviceName, '1']
        );

        if (!$device) {
            throw new NotFoundHttpException('Simulator device not found or disabled.');
        }

        $deviceId = (string) ($device['id'] ?? '');
        if ($deviceId === '') {
            throw new NotFoundHttpException('Simulator device id missing.');
        }

        $areas = $this->extractAreas($device);
        $limit = 1;

        $jobs = $this->doorJobService->dispatchJobs($deviceId, $areas, $limit);

        return [
            'jobs' => array_map(static function (array $job): array {
                return [
                    'jobId' => $job['jobId'] ?? $job['id'] ?? null,
                    'area' => $job['area'] ?? $job['areaKey'] ?? null,
                    'action' => $job['action'] ?? 'open',
                    'nonce' => $job['nonce'] ?? null,
                    'expiresInMs' => $job['expiresInMs'] ?? null,
                    'correlationId' => $job['correlationId'] ?? null,
                ];
            }, $jobs),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function confirm(array $payload, string $deviceName = 'shed-simulator'): array
    {
        $device = $this->connection->fetchAssociative(
            'SELECT * FROM tl_co_device WHERE name = ? AND enabled = ? LIMIT 1',
            [$deviceName, '1']
        );

        if (!$device) {
            throw new NotFoundHttpException('Simulator device not found or disabled.');
        }

        $deviceId = (string) ($device['id'] ?? '');
        $jobId = (int) ($payload['jobId'] ?? 0);
        $nonce = trim((string) ($payload['nonce'] ?? ''));
        $ok = (bool) ($payload['ok'] ?? true);
        $meta = \is_array($payload['meta'] ?? null) ? $payload['meta'] : ['source' => 'door-simulator'];
        $correlationId = isset($payload['correlationId']) ? (string) $payload['correlationId'] : null;

        if ($jobId <= 0 || '' === $nonce) {
            throw new BadRequestHttpException('jobId and nonce are required.');
        }

        $result = $this->doorJobService->confirmJobDetailed(
            $deviceId,
            $jobId,
            $nonce,
            $ok,
            $meta
        );

        return [
            'ok' => true,
            'result' => $result,
        ];
    }

    /**
     * @param array<string, mixed> $device
     * @return list<string>
     */
    private function extractAreas(array $device): array
    {
        $areasRaw = $device['areas'] ?? '';

        if (!is_string($areasRaw) || $areasRaw === '') {
            return [];
        }

        $areas = @unserialize($areasRaw);

        if (is_array($areas)) {
            return array_values(array_filter(array_map('strval', $areas)));
        }

        return [];
    }
}
