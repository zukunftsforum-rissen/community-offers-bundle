<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Api;

use Contao\FrontendUser;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorAuditLogger;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

#[Route('/api/door', defaults: ['_scope' => 'frontend', '_token_check' => false])]
final class AccessController
{
    public function __construct(
        private readonly Security $security,
        private readonly AccessService $accessService,
        private readonly AccessRequestService $accessRequestService,
        private readonly DoorJobService $doorJobs,
        private readonly LoggingService $logging,
        private readonly CacheItemPoolInterface $cache,
        private readonly DoorAuditLogger $audit,
    ) {}

    #[Route('/whoami', name: 'community_offers_whoami', methods: ['GET'])]
    public function whoami(Request $request): JsonResponse
    {
        $this->logging->initiateLogging('door', 'community-offers');
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
        $this->logging->initiateLogging('door', 'community-offers');
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
            // NOTE: Das ist die echte Methode in AccessRequestService.php
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

        $code = (string) ($result['code'] ?? 'unknown');

        $this->logging->info('request_access.result', [
            'memberId' => $memberId,
            'slug' => $slug,
            'code' => $code,
            'ip' => $request->getClientIp(),
        ]);

        if ($code === 'pending_confirmed') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Anfrage ist bereits bestätigt und wartet auf Freigabe.',
            ], 409);
        }

        if ($code === 'cooldown') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Bitte warte kurz, bevor du erneut anforderst.',
                'retryAfterSeconds' => (int) ($result['retryAfterSeconds'] ?? 300),
            ], 429);
        }

        if ($code === 'invalid_email') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid email',
            ], 400);
        }

        if ($code !== 'ok') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Could not create request',
            ], 500);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'DOI email sent',
            // UX-Hinweis (cooldown ist im Service 600s)
            'retryAfterSeconds' => 600,
        ], 200);
    }

    #[Route('/open/{slug}', name: 'community_offers_open_door', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function open(Request $request, string $slug): JsonResponse
    {
        $this->logging->initiateLogging('door', 'community-offers');
        $this->logging->start('open', ['slug' => $slug]);

        $user = $this->security->getUser();
        if (!$user instanceof FrontendUser) {
            return new JsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $memberId = (int) $user->id;

        // Rechte prüfen
        $areas = $this->accessService->getGrantedAreasForMemberId($memberId);
        if (!\in_array($slug, $areas, true)) {
            $this->logging->info('open.forbidden', [
                'memberId' => $memberId,
                'slug' => $slug,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Best effort housekeeping
        $this->doorJobs->expireOldJobs();

        $result = $this->doorJobs->createOpenJob(
            memberId: $memberId,
            area: $slug,
            ip: (string) ($request->getClientIp() ?? ''),
            userAgent: (string) $request->headers->get('User-Agent', ''),
        );

        $status = (int) ($result['httpStatus'] ?? 500);

        $payload = [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
        ];

        if (isset($result['jobId'])) {
            $payload['accepted'] = true;
            $payload['jobId'] = (int) $result['jobId'];
            if (isset($result['status'])) {
                $payload['status'] = (string) $result['status'];
            }
            if (isset($result['expiresAt'])) {
                $payload['expiresAt'] = (int) $result['expiresAt'];
            }
        } else {
            $payload['accepted'] = false;
        }

        if (isset($result['retryAfterSeconds'])) {
            $payload['retryAfterSeconds'] = (int) $result['retryAfterSeconds'];
        }

        // optional: Header bei 429
        $response = new JsonResponse($payload, $status);
        if ($status === 429 && isset($result['retryAfterSeconds'])) {
            $response->headers->set('Retry-After', (string) (int) $result['retryAfterSeconds']);
        }

        // Audit/Log (ohne Annahmen über konkrete Audit-API)
        $this->logging->info('open.result', [
            'memberId' => $memberId,
            'slug' => $slug,
            'httpStatus' => $status,
            'ok' => (bool) ($result['ok'] ?? false),
            'jobId' => $result['jobId'] ?? null,
        ]);

        return $response;
    }
}
