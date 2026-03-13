<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Device\Service;

use Doctrine\DBAL\Connection;

final class DeviceRateLimitService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function isPollAllowed(string $deviceId, int $minIntervalSeconds = 2): bool
    {
        $lastSeen = $this->connection->fetchOne(
            'SELECT lastSeen FROM tl_co_device WHERE deviceId = ?',
            [$deviceId],
        );

        if (false === $lastSeen || null === $lastSeen) {
            return true;
        }

        return (int) $lastSeen + $minIntervalSeconds <= time();
    }
}
