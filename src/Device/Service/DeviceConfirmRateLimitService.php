<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Device\Service;

use Psr\Cache\CacheItemPoolInterface;

final class DeviceConfirmRateLimitService
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function isAllowed(string $deviceId, int $limit = 10, int $rateLimitWindowSeconds = 300): bool
    {
        $now = time();
        $key = 'device_confirm_fail_'.$deviceId;
        $item = $this->cache->getItem($key);
        $data = $item->isHit() ? $item->get() : null;

        if (!\is_array($data) || !isset($data['count'], $data['resetAt'])) {
            return true;
        }

        if ($now >= (int) $data['resetAt']) {
            return true;
        }

        return (int) $data['count'] < $limit;
    }

    public function registerFailure(string $deviceId, int $rateLimitWindowSeconds = 300): void
    {
        $now = time();
        $key = 'device_confirm_fail_'.$deviceId;
        $item = $this->cache->getItem($key);
        $data = $item->isHit() ? $item->get() : null;

        if (!\is_array($data) || !isset($data['count'], $data['resetAt']) || $now >= (int) $data['resetAt']) {
            $data = [
                'count' => 0,
                'resetAt' => $now + $rateLimitWindowSeconds,
            ];
        }

        $data['count'] = (int) $data['count'] + 1;

        $item->set($data);
        $item->expiresAfter(max(1, (int) $data['resetAt'] - $now));
        $this->cache->save($item);
    }

    public function reset(string $deviceId): void
    {
        $this->cache->deleteItem('device_confirm_fail_'.$deviceId);
    }
}
