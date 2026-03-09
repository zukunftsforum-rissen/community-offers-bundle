<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller;

use Contao\BackendUser;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DeviceMonitorService;

final class DeviceMonitorController extends AbstractController
{
    public function __construct(
        private readonly DeviceMonitorService $deviceMonitorService,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof BackendUser) {
            throw new AccessDeniedException('Backend login required.');
        }

        return $this->render('@CommunityOffers/monitor/device_monitor.html.twig', [
            'title' => 'Device Monitor',
            'backendRoutePrefix' => (string) $this->getParameter('contao.backend.route_prefix'),
            'devices' => $this->deviceMonitorService->getOverview(),
        ]);
    }
}
