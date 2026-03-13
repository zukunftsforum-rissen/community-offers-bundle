<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Simulator;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Simulator\SimulatorDeviceService;

final class DoorSimulatorConfirmController extends AbstractController
{
    public function __construct(
        private readonly SimulatorDeviceService $simulatorDeviceService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);

        if (!\is_array($payload)) {
            $payload = [];
        }

        return $this->json(
            $this->simulatorDeviceService->confirm($payload, SimulatorDeviceService::SIMULATOR_DEVICE_ID)
        );
    }
}
