<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayResult;

interface DoorOpenObserverInterface
{
    public function onForbidden(
        int $memberId,
        string $area,
        string $ip,
        string $correlationId,
        string $mode,
    ): void;

    public function onResult(
        int $memberId,
        string $area,
        string $ip,
        string $userAgent,
        string $correlationId,
        string $mode,
        DoorGatewayResult $result,
    ): void;
}
