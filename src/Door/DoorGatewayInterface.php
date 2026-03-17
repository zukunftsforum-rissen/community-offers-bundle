<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Door;

interface DoorGatewayInterface
{
    public function supports(string $mode): bool;

    /**
     * @param array<string, mixed> $context
     */
    public function open(string $area, int $memberId, array $context = []): DoorGatewayResult;
}
