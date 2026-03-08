<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Backend;

use Contao\BackendUser;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Controller\Backend\AbstractBackendController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorWorkflowDiagramService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorWorkflowTimelineService;

#[Route(
    path: '%contao.backend.route_prefix%/door-workflow',
    name: 'community_offers.door_workflow_inspector',
    defaults: ['_scope' => 'backend'],
)]
#[IsGranted('ROLE_USER')]
final class DoorWorkflowInspectorController extends AbstractBackendController
{
    public function __construct(
        private readonly DoorWorkflowTimelineService $timelineService,
        private readonly DoorWorkflowDiagramService $diagramService,
        private readonly Security $security,
    ) {}

    #[Route('', name: 'co_be_door_workflow_inspector', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser || !$user->isAdmin) {
            throw new AccessDeniedException('Access denied.');
        }

        $cid = trim((string) $request->query->get('cid', ''));
        $recent = $this->timelineService->getRecent(50);

        $timeline = [];
        $plantUml = '';
        $durationMs = null;

        if ('' !== $cid) {
            $timeline = $this->timelineService->getTimeline($cid);
            $plantUml = $this->diagramService->buildPlantUml($cid, $timeline);
            $durationMs = $this->timelineService->getDurationMs($cid);
            $diagramUrl = $this->diagramService->buildServerSvgUrl($plantUml);
        }

        $warnings = [];

        if ($timeline) {
            $warnings = $this->timelineService->analyzeWorkflow($timeline);
        }

        $diagramUrl = null;

        return $this->render(
            'be_door_workflow_inspector.html.twig',
            [
                'cid' => $cid,
                'recent' => $recent,
                'timeline' => $timeline,
                'plantUml' => $plantUml,
                'durationMs' => $durationMs,
                'diagramUrl' => $diagramUrl,
                'warnings' => $warnings,
            ],
        );
    }

    #[Route('/diagram/{cid}.svg', name: 'co_be_door_workflow_diagram', methods: ['GET'])]
    public function diagram(string $cid): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser || !$user->isAdmin) {
            throw new AccessDeniedException('Access denied.');
        }

        $timeline = $this->timelineService->getTimeline($cid);
        $plantUml = $this->diagramService->buildPlantUml($cid, $timeline);
        $diagramUrl = $this->diagramService->buildServerSvgUrl($plantUml);
        $svg = file_get_contents($diagramUrl);

        return new Response(
            $svg,
            200,
            [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
            ],
        );
    }
}
