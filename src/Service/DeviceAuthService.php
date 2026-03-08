<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;

final class DeviceAuthService
{
    public function __construct(
        private readonly Connection $db,
        private readonly LoggingService $logging,
    ) {}


    /**
     * @return array{deviceId:string, areas:?array<string>}|null
     */
    public function authenticate(string|null $token): array|null
    {
        if (!$token) {
            $this->logging->warning('device_auth.no_token');

            return null;
        }

        $hash = hash('sha256', $token);

        $this->logging->info('device_auth.lookup_start', [
            'tokenPrefix' => substr($token, 0, 8),
            'tokenHashPrefix' => substr($hash, 0, 12),
        ]);

        $device = $this->db->fetchAssociative(
            'SELECT id, deviceId, enabled, areas, apiTokenHash
                    FROM tl_co_device
                    WHERE apiTokenHash = :hash
                    LIMIT 1',
            ['hash' => $hash]
        );

        if (!$device) {
            $this->logging->warning('device_auth.lookup_not_found', [
                'tokenHashPrefix' => substr($hash, 0, 12),
            ]);

            return null;
        }

        if ((string) ($device['enabled'] ?? '') !== '1') {
            $this->logging->warning('device_auth.lookup_disabled', [
                'deviceId' => (string) ($device['deviceId'] ?? ''),
                'enabled' => (string) ($device['enabled'] ?? ''),
                'tokenHashPrefix' => substr($hash, 0, 12),
            ]);

            return null;
        }

        $deviceId = (string) $device['deviceId'];

        $areasRaw = $device['areas'] ?? null;
        $areasArr = array_values(array_map('strval', StringUtil::deserialize($areasRaw, true)));
        $areas = array_values(array_unique(array_filter(array_map('strval', $areasArr))));

        $this->logging->info('device_auth.lookup_success', [
            'deviceId' => $deviceId,
            'areas' => $areas,
            'tokenHashPrefix' => substr($hash, 0, 12),
        ]);

        return ['deviceId' => $deviceId, 'areas' => $areas];
    }
}
