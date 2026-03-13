<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Simulator;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use ZukunftsforumRissen\CommunityOffersBundle\Service\SimulatorDeviceService;

final class DoorSimulatorController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('@CommunityOffers/simulator/simulator.html.twig', [
            'title' => 'Door Simulator',
            'deviceName' => SimulatorDeviceService::SIMULATOR_DEVICE_ID,
            'pollIntervalMs' => 2000,
            'pollUrl' => '/door-simulator/poll',
            'confirmUrl' => '/door-simulator/confirm',
            'sheds' => [
                [
                    'key' => 'shed-1',
                    'label' => 'Schuppen 1',
                    'doors' => [
                        ['area' => 'depot', 'label' => 'Depot'],
                        ['area' => 'swap-house', 'label' => 'Tauschhaus'],
                    ],
                ],
                [
                    'key' => 'shed-2',
                    'label' => 'Schuppen 2',
                    'doors' => [
                        ['area' => 'workshop', 'label' => 'Werkstatt'],
                        ['area' => 'sharing', 'label' => 'Ausleihe'],
                    ],
                ],
            ],
        ]);
    }
}
