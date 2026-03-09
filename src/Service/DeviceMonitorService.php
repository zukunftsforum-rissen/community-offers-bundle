<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Doctrine\DBAL\Connection;

final class DeviceMonitorService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function getOverview(): array
    {
        $devices = $this->connection->fetchAllAssociative(
            'SELECT id, name, enabled, areas, lastSeen, tstamp FROM tl_co_device ORDER BY name ASC'
        );

        $result = [];

        foreach ($devices as $device) {
            $deviceId = (int) ($device['id'] ?? 0);

            $lastPoll = $this->connection->fetchAssociative(
                'SELECT tstamp, area, correlationId
                 FROM tl_co_door_log
                 WHERE deviceId = :deviceId
                   AND action IN (\'device_poll\', \'door_dispatch\')
                 ORDER BY id DESC
                 LIMIT 1',
                ['deviceId' => $deviceId]
            );

            $lastConfirm = $this->connection->fetchAssociative(
                'SELECT tstamp, area, correlationId
                 FROM tl_co_door_log
                 WHERE deviceId = :deviceId
                   AND action = \'door_confirm\'
                 ORDER BY id DESC
                 LIMIT 1',
                ['deviceId' => $deviceId]
            );

            $lastPollAt = $this->formatTimestamp($lastPoll['tstamp'] ?? null);
            $lastConfirmAt = $this->formatTimestamp($lastConfirm['tstamp'] ?? null);
            $lastArea = $lastPoll['area'] ?? ($lastConfirm['area'] ?? null);

            $result[] = [
                'id' => $deviceId,
                'name' => (string) ($device['name'] ?? ('Device #' . $deviceId)),
                'enabled' => (string) ($device['enabled'] ?? '') === '1',
                'areas' => $this->normalizeAreas($device['areas'] ?? ''),
                'lastPollAt' => $lastPollAt,
                'lastConfirmAt' => $lastConfirmAt,
                'lastArea' => $lastArea,
                'onlineState' => $this->deriveOnlineState($lastPollAt, (int) ($device['lastSeen'] ?? 0)),
                'lastCorrelationId' => $lastPoll['correlationId'] ?? ($lastConfirm['correlationId'] ?? null),
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function normalizeAreas(mixed $raw): array
    {
        if (\is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        if (!\is_string($raw) || '' === $raw) {
            return [];
        }

        $decoded = @unserialize($raw);

        if (\is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return [];
    }

    private function deriveOnlineState(?string $lastPollAt, int $lastSeen): string
    {
        $ts = null;

        if ($lastPollAt) {
            $parsed = strtotime($lastPollAt);
            if (false !== $parsed) {
                $ts = $parsed;
            }
        }

        if (($ts === null || $ts <= 0) && $lastSeen > 0) {
            $ts = $lastSeen;
        }

        if ($ts === null || $ts <= 0) {
            return 'unknown';
        }

        $age = time() - $ts;

        if ($age <= 15) {
            return 'online';
        }

        if ($age <= 120) {
            return 'idle';
        }

        return 'offline';
    }

    private function formatTimestamp(mixed $value): ?string
    {
        $ts = (int) $value;

        if ($ts <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
