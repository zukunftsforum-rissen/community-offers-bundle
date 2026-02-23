<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Door;

interface DoorGatewayInterface
{
    /**
     * Öffnet eine Tür / schaltet ein Relais.
     * Gibt true zurück, wenn der Trigger erfolgreich abgesetzt wurde.
     */
    public function open(string $area, int $memberId): bool;
}
