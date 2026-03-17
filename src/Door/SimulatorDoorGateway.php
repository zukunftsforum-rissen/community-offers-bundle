<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Door;

use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

final class SimulatorDoorGateway implements DoorGatewayInterface
{
    public const TYPE = 'simulation';

    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function supports(string $mode): bool
    {
        return self::TYPE === $mode;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function open(string $area, int $memberId, array $context = []): DoorGatewayResult
    {
        $this->logging->initiateLogging('door', 'community-offers');
        $this->logging->info('door.gateway.simulator_open', [
            'area' => $area,
            'memberId' => $memberId,
        ] + $context);

        return DoorGatewayResult::success(
            status: 'simulator_opened',
            message: 'Simulator door open triggered.',
            httpStatus: 200,
            context: [
                'area' => $area,
                'memberId' => $memberId,
                'mode' => self::TYPE,
            ] + $context,
        );
    }
}
