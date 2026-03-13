<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Device\Service;

interface DeviceHeartbeatInterface
{
    /**
     * @param array<int, string> $areas
     */
    public function registerPoll(int|string $deviceId, array $areas = []): void;
}
