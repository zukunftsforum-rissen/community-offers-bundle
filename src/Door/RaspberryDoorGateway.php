<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Door;

final class RaspberryDoorGateway implements DoorGatewayInterface
{
    public function open(string $area, int $memberId): bool
    {
        // echte Live-Anbindung
        return true;
    }
}
