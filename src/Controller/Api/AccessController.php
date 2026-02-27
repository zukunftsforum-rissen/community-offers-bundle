<?php

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/door', defaults: ['_scope' => 'frontend'])]
final class AccessController
{
    public function __construct(
        private readonly Connection $db,
        private readonly Security $security,
        // private readonly C3LockService $c3Lock, // <- bei euch: serverseitige Rechteprüfung
    ) {}

    #[Route('/open/{area}', name: 'co_door_open', methods: ['POST'])]
    public function open(string $area, Request $request): JsonResponse
    {
        $member = $this->security->getUser();
        if (!$member) {
            return new JsonResponse(['success' => false, 'error' => 'unauthorized'], 401);
        }

        // Contao FE User: memberId verlässlich als ->id
        $memberId = (int)($member->id ?? 0);
        if ($memberId <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'unauthorized'], 401);
        }

        // Area whitelist (zusätzlich zu Routing)
        $allowedAreas = ['depot', 'swap-house', 'workshop', 'sharing'];
        if (!in_array($area, $allowedAreas, true)) {
            return new JsonResponse(['success' => false, 'error' => 'invalid_area'], 404);
        }

        // 1) Serverseitige Rechteprüfung (C3 Lock)
        // $this->c3Lock->assertCanOpen($member, $area);

        $now = time();
        $ttl = 10;
        $expiresAt = $now + $ttl;

        // 2) Best effort: abgelaufene Jobs auf expired setzen
        $this->db->executeStatement(
            "UPDATE tl_co_door_job
             SET status='expired'
             WHERE status IN ('pending','dispatched')
               AND expiresAt > 0
               AND expiresAt < :now",
            ['now' => $now]
        );

        // 3) Rate-Limit + “nur 1 aktiver Job pro member+area”
        // a) Wenn es einen aktiven Job gibt (pending/dispatched, nicht abgelaufen), geben wir den zurück (idempotent)
        $active = $this->db->fetchAssociative(
            "SELECT id, expiresAt, status
             FROM tl_co_door_job
             WHERE requestedByMemberId = :memberId
               AND area = :area
               AND status IN ('pending','dispatched')
               AND (expiresAt = 0 OR expiresAt >= :now)
             ORDER BY createdAt DESC
             LIMIT 1",
            [
                'memberId' => $memberId,
                'area' => $area,
                'now' => $now,
            ]
        );

        if ($active) {
            return new JsonResponse([
                'success' => true,
                'accepted' => true,
                'jobId' => (int)$active['id'],
                'status' => (string)$active['status'],
                'expiresAt' => (int)$active['expiresAt'],
            ], 202);
        }

        // b) Zusätzliches “burst” Rate-Limit: max 1 request / 2s (Beispiel)
        $recentCount = (int)$this->db->fetchOne(
            "SELECT COUNT(*)
             FROM tl_co_door_job
             WHERE requestedByMemberId = :memberId
               AND area = :area
               AND createdAt >= :since",
            [
                'memberId' => $memberId,
                'area' => $area,
                'since' => $now - 2,
            ]
        );

        if ($recentCount > 0) {
            return new JsonResponse([
                'success' => false,
                'error' => 'rate_limited',
            ], 429);
        }

        // 4) Job erzeugen
        $ip = (string)($request->getClientIp() ?? '');
        $ua = substr((string)$request->headers->get('User-Agent', ''), 0, 255);

        $this->db->insert('tl_co_door_job', [
            'tstamp' => $now,
            'createdAt' => $now,
            'expiresAt' => $expiresAt,
            'area' => $area,
            'status' => 'pending',
            'requestedByMemberId' => $memberId,
            'requestIp' => $ip,
            'userAgent' => $ua,

            // dispatch/nonce/result default leer:
            'dispatchToDeviceId' => '',
            'dispatchedAt' => 0,
            'executedAt' => 0,
            'nonce' => '',
            'attempts' => 0,
            'resultCode' => '',
            'resultMessage' => '',
        ]);

        $jobId = (int) $this->db->lastInsertId();

        return new JsonResponse([
            'success' => true,
            'accepted' => true,
            'jobId' => $jobId,
            'expiresAt' => $expiresAt,
        ], 202);
    }
}