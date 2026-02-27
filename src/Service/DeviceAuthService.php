<?php

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/** @package ZukunftsforumRissen\CommunityOffersBundle\Service */
final class DeviceAuthService
{
    public function __construct(private readonly Connection $db) {}

    /**
     * @return array{deviceId:string, areas:string[]}|null
     */
    public function authenticate(?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        $hash = hash('sha256', $token);

        $device = $this->db->fetchAssociative(
            "SELECT deviceId, enabled, areas
             FROM tl_co_device
             WHERE apiTokenHash = :hash
             LIMIT 1",
            ['hash' => $hash]
        );

        if (!$device || ($device['enabled'] ?? ' ') !== '1') {
            return null;
        }

        $deviceId = (string) $device['deviceId'];

        $areasRaw = $device['areas'] ?? null;
        $areasArr = StringUtil::deserialize($areasRaw, true);
        $areas = array_values(array_unique(array_filter(array_map('strval', $areasArr))));

        return ['deviceId' => $deviceId, 'areas' => $areas];
    }
}
