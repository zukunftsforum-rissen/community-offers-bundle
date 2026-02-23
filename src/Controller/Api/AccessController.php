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
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

#[Route('/api/door', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class AccessController
{
    public function __construct(
        private readonly Security $security,
        private readonly AccessService $accessService,
        private readonly AccessRequestService $accessRequestService,
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

        $requests = $this->accessRequestService
            ->getPendingRequestsForEmail((string) $user->email);

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
                'lastname'  => $user->lastname ?? null,
                'email'     => $user->email ?? null,
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
        if (!in_array($slug, $known, true)) {
            $this->logging->info('request_access.unknown_area', [
                'memberId' => (int) $user->id,
                'slug' => $slug,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Unknown area'], 404);
        }

        $memberId = (int) $user->id;
        $granted = $this->accessService->getGrantedAreasForMemberId($memberId);

        if (in_array($slug, $granted, true)) {
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

        $this->logging->info('request_access.result', [
            'memberId' => $memberId,
            'slug' => $slug,
            'code' => $result['code'] ?? 'unknown',
            'ip' => $request->getClientIp(),
        ]);

        if (($result['code'] ?? '') === 'pending_confirmed') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Anfrage ist bereits bestätigt und wartet auf Freigabe.',
            ], 409);
        }

        if (($result['code'] ?? '') === 'cooldown') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Bitte warte kurz, bevor du erneut anforderst.',
                'retryAfterSeconds' => (int) ($result['retryAfterSeconds'] ?? 300),
            ], 429);
        }

        if (($result['code'] ?? '') === 'invalid_email') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid email',
            ], 400);
        }

        if (($result['code'] ?? '') !== 'ok') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Could not create request',
            ], 500);
        }

        // Optional (für Punkt 2 UX): retryAfterSeconds mitgeben
        return new JsonResponse([
            'success' => true,
            'message' => 'DOI email sent',
            'retryAfterSeconds' => 600,
        ], 200);
    }

    #[Route('/open/{slug}', name: 'community_offers_open_door', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function open(Request $request, string $slug): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return new JsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $memberId = (int) $user->id;

        // Rechte prüfen (nur wenn AccessService das kann)
        $areas = $this->accessService->getGrantedAreasForMemberId($memberId);
        if (!in_array($slug, $areas, true)) {
            return new JsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // --- Rate limit: 3/min Member+Area ---
        $limit = 3;
        $windowSeconds = 60;
        $now = time();

        $rateKey = sprintf('door_open_m%d_%s', $memberId, $slug); // keine ":" !
        $rateItem = $this->cache->getItem($rateKey);
        $data = $rateItem->isHit() ? $rateItem->get() : null;

        if (!is_array($data) || !isset($data['count'], $data['resetAt'])) {
            $data = ['count' => 0, 'resetAt' => $now + $windowSeconds];
        }

        if ($now >= (int) $data['resetAt']) {
            $data = ['count' => 0, 'resetAt' => $now + $windowSeconds];
        }

        if ((int) $data['count'] >= $limit) {
            $retryAfterSeconds = max(1, (int) $data['resetAt'] - $now);
            return new JsonResponse([
                'success' => false,
                'message' => 'Zu viele Versuche – bitte kurz warten.',
                'retryAfterSeconds' => $retryAfterSeconds,
            ], 429);
        }

        $data['count'] = (int) $data['count'] + 1;
        $rateItem->set($data);
        $rateItem->expiresAfter(max(1, (int) $data['resetAt'] - $now));
        $this->cache->save($rateItem);

        // --- C3 Locks: Member+Area + global Area ---
        $lockSeconds = 5;
        $until = $now + $lockSeconds;

        $memberLockKey = sprintf('door_lock_member_m%d_%s', $memberId, $slug);
        $memberLock = $this->cache->getItem($memberLockKey);
        if ($memberLock->isHit()) {
            $payload = $memberLock->get();
            $retry = is_array($payload) && isset($payload['until']) ? ((int)$payload['until'] - $now) : $lockSeconds;
            return new JsonResponse([
                'success' => false,
                'message' => 'Tür wurde gerade geöffnet.',
                'retryAfterSeconds' => max(1, $retry),
            ], 429);
        }

        $areaLockKey = sprintf('door_lock_area_%s', $slug);
        $areaLock = $this->cache->getItem($areaLockKey);
        if ($areaLock->isHit()) {
            $payload = $areaLock->get();
            $retry = is_array($payload) && isset($payload['until']) ? ((int)$payload['until'] - $now) : $lockSeconds;
            return new JsonResponse([
                'success' => false,
                'message' => 'Tür ist gerade in Benutzung.',
                'retryAfterSeconds' => max(1, $retry),
            ], 429);
        }

        // --- Tür öffnen (hier später Hardware) ---
        $ok = $this->accessService->openDoor($slug, $memberId);

        // Locks nur bei Erfolg setzen
        if ($ok) {
            $memberLock->set(['until' => $until]);
            $memberLock->expiresAfter($lockSeconds);
            $this->cache->save($memberLock);

            $areaLock->set(['until' => $until]);
            $areaLock->expiresAfter($lockSeconds);
            $this->cache->save($areaLock);
        }

        return new JsonResponse([
            'success' => $ok,
            'door' => $slug,
        ], $ok ? 200 : 500);
    }
}
