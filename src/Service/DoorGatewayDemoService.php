<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayResolver;
use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayResult;

final class DoorGatewayDemoService
{
    public function __construct(
        private readonly DoorGatewayResolver $resolver,
    ) {
    }

    public function openWithGateway(string $deviceType, string $area, int $memberId): DoorGatewayResult
    {
        $gateway = $this->resolver->resolve($deviceType);

        return $gateway->open($area, $memberId);
    }
}
