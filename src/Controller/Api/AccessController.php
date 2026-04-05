<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Api;

use Contao\FrontendUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\CorrelationIdService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorAuditLogger;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorWorkflowLogger;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\OpenDoorService;

#[Route('/api/door', defaults: ['_scope' => 'frontend', '_token_check' => false])]
final class AccessController
{
    public function __construct(
        private readonly Security $security,
        private readonly AccessService $accessService,
        private readonly AccessRequestService $accessRequestService,
        private readonly OpenDoorService $openDoorService,
        private readonly LoggingService $logging,
        private readonly DoorAuditLogger $audit,
        private readonly DoorWorkflowLogger $doorWorkflowLogger,
        private readonly CorrelationIdService $correlationIds,
    ) {
    }

    #[Route('/whoami', name: 'community_offers_whoami', methods: ['GET'])]
    public function whoami(Request $request): JsonResponse
    {
        $this->logging->start('whoami');

        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            $this->logging->info('whoami.anon', [
                'ip' => $request->getClientIp(),
                'ua' => $request->headers->get('User-Agent'),
            ]);

            return new JsonResponse([
                'authenticated' => false,
                'areas' => [],
            ]);
        }

        $memberId = (int) $user->id;
        $areas = $this->accessService->getGrantedAreasForMemberId($memberId);

        $requests = $this->accessRequestService->getPendingRequestsForEmail((string) $user->email);

        $this->logging->debug('whoami.auth', [
            'memberId' => $memberId,
            'areas' => $areas,
            'requests' => $requests,
            'ip' => $request->getClientIp(),
        ]);

        return new JsonResponse([
            'authenticated' => true,
            'member' => [
                'id' => $memberId,
                'firstname' => $user->firstname ?? null,
                'lastname' => $user->lastname ?? null,
                'email' => $user->email ?? null,
            ],
            'areas' => $areas,
            'requests' => $requests,
        ]);
    }

    #[Route('/request/{slug}', name: 'community_offers_request_access', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function request(Request $request, string $slug): JsonResponse
    {
        $this->logging->start('request_access', ['slug' => $slug]);

        $user = $this->security->getUser();
        if (!$user instanceof FrontendUser) {
            $this->logging->info('request_access.unauthenticated', [
                'slug' => $slug,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $known = $this->accessService->getKnownAreas();
        if (!\in_array($slug, $known, true)) {
            $this->logging->info('request_access.unknown_area', [
                'memberId' => (int) $user->id,
                'slug' => $slug,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Unknown area'], 404);
        }

        $memberId = (int) $user->id;
        $granted = $this->accessService->getGrantedAreasForMemberId($memberId);

        if (\in_array($slug, $granted, true)) {
            $this->logging->info('request_access.already_granted', [
                'memberId' => $memberId,
                'slug' => $slug,
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Already granted'], 400);
        }

        try {
            $result = $this->accessRequestService->sendOrResendDoiForArea(
                firstname: (string) ($user->firstname ?? ''),
                lastname: (string) ($user->lastname ?? ''),
                email: (string) ($user->email ?? ''),
                area: $slug,
            );
        } catch (\Throwable $e) {
            $this->logging->error('request_access.exception', [
                'memberId' => $memberId,
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Internal error'], 500);
        }

        $code = (string) $result['code'];

        $this->logging->info('request_access.result', [
            'memberId' => $memberId,
            'slug' => $slug,
            'code' => $code,
            'ip' => $request->getClientIp(),
        ]);

        if ('pending_confirmed' === $code) {
            return new JsonResponse(
                [
                    'success' => false,
                    'message' => 'Anfrage ist bereits bestätigt und wartet auf Freigabe.',
                ],
                409,
            );
        }

        if ('cooldown' === $code) {
            return new JsonResponse(
                [
                    'success' => false,
                    'message' => 'Bitte warte kurz, bevor du erneut anforderst.',
                    'retryAfterSeconds' => (int) ($result['retryAfterSeconds'] ?? 300),
                ],
                429,
            );
        }

        if ('invalid_email' === $code) {
            return new JsonResponse(
                [
                    'success' => false,
                    'message' => 'Invalid email',
                ],
                400,
            );
        }

        if ('ok' !== $code) {
            return new JsonResponse(
                [
                    'success' => false,
                    'message' => 'Could not create request',
                ],
                500,
            );
        }

        return new JsonResponse(
            [
                'success' => true,
                'message' => 'DOI email sent',
                'retryAfterSeconds' => 600,
            ],
            200,
        );
    }

    #[Route('/open/{slug}', name: 'community_offers_open_door', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function open(Request $request, string $slug): JsonResponse
    {
        $cid = $this->correlationIds->create();

        $user = $this->security->getUser();
        if (!$user instanceof FrontendUser) {
            $this->doorWorkflowLogger->openForbidden([
                'cid' => $cid,
                'slug' => $slug,
                'ip' => $request->getClientIp(),
            ]);

            $this->audit->audit(
                action: 'door_open',
                area: $slug,
                result: 'unauthenticated',
                message: 'Door open without authenticated frontend user',
                correlationId: $cid,
                context: ['slug' => $slug],
            );

            return new JsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $memberId = (int) $user->id;

        $this->doorWorkflowLogger->openAttempt(
            [
                'cid' => $cid,
                'memberId' => $memberId,
                'slug' => $slug,
                'ip' => $request->getClientIp(),
            ],
        );

        $result = $this->openDoorService->open(
            memberId: $memberId,
            area: $slug,
            ip: (string) ($request->getClientIp() ?? ''),
            userAgent: (string) $request->headers->get('User-Agent', ''),
            correlationId: $cid,
        );

        $status = (int) $result['httpStatus'];

        $payload = [
            'success' => (bool) $result['ok'],
            'message' => (string) $result['message'],
            'correlationId' => $cid,
            'accepted' => (bool) $result['accepted'],
            'mode' => (string) $result['mode'],
        ];

        if (isset($result['jobId'])) {
            $payload['jobId'] = (int) $result['jobId'];
        }

        if (isset($result['status'])) {
            $payload['status'] = (string) $result['status'];
        }

        if (isset($result['expiresAt'])) {
            $payload['expiresAt'] = (int) $result['expiresAt'];
        }

        if (isset($result['retryAfterSeconds'])) {
            $payload['retryAfterSeconds'] = (int) $result['retryAfterSeconds'];
        }

        $response = new JsonResponse($payload, $status);

        if (429 === $status && isset($result['retryAfterSeconds'])) {
            $response->headers->set('Retry-After', (string) (int) $result['retryAfterSeconds']);
        }

        return $response;
    }
}
