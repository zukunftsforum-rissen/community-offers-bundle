<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use ZukunftsforumRissen\CommunityOffersBundle\Service\SimulatorDeviceService;

final class DoorSimulatorPollController extends AbstractController
{
    public function __construct(
        private readonly SimulatorDeviceService $simulatorDeviceService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->simulatorDeviceService->poll('shed-simulator'));
    }
}
