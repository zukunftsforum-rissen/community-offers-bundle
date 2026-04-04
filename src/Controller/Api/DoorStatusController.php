<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Api;

use Contao\FrontendUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;

#[Route('/api/door', defaults: ['_scope' => 'frontend'])]
final class DoorStatusController extends AbstractController
{
    public function __construct(
        private readonly DoorJobService $doorJobService,
    ) {
    }

    #[Route('/status/{jobId}', name: 'community_offers_door_status', methods: ['GET'])]
    public function status(int $jobId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof FrontendUser) {
            return new JsonResponse(
                [
                    'success' => false,
                    'message' => 'unauthorized',
                ],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $result = $this->doorJobService->getJobStatus($jobId, (int) $user->id);

        return new JsonResponse(
            [
                'success' => $result['ok'],
                'message' => $result['message'],
                'jobId' => $result['jobId'] ?? null,
                'status' => $result['status'] ?? null,
                'area' => $result['area'] ?? null,
                'expiresAt' => $result['expiresAt'] ?? null,
            ],
            $result['httpStatus'],
        );
    }
}
