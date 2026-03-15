<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Door;

use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

class DemoDoorGateway implements DoorGatewayInterface
{
    public function __construct(
        private readonly LoggingService $logging
    ) {
    }

    public function open(string $area, int $memberId): bool
    {
        // Demo: tut so, als ob die Tür geöffnet wurde
        $this->logging->initiateLogging('door', 'community-offers');
        $this->logging->info('door.gateway.demo_open', [
            'area' => $area,
            'memberId' => $memberId,
        ]);

        return true;
    }
}
