<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

class AccessService
{
    public function openDoor(string $slug): bool
    {
        // TODO: später Rechteprüfung
        // TODO: später Hardware-Trigger

        return true; // Prototyp
    }
}
