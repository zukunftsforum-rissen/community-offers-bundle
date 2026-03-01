<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use ZukunftsforumRissen\CommunityOffersBundle\Security\DeviceApiUser;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;

#[Route('/api/device')]
final class DeviceController extends AbstractController
{
    public function __construct(private readonly DoorJobService $jobs)
    {
    }

    #[Route('/poll', name: 'co_device_poll', methods: ['POST'])]
    public function poll(Request $request): JsonResponse
    {
        /** @var DeviceApiUser $user */
        $user = $this->getUser();
        if (!$user instanceof DeviceApiUser) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }
        $deviceId = $user->getDeviceId();
        $areas = $user->getAreas();

        $this->jobs->expireOldJobs();

        $payload = json_decode((string) $request->getContent(), true);
        $limit = (int) ($payload['limit'] ?? 3);
        $claimed = $this->jobs->dispatchJobs($deviceId, $areas, $limit);

        $jobs = [];

        /** @var list<array{jobId:int, area:string, nonce:string, expiresInMs:int}> $claimed */
        foreach ($claimed as $job) {
            $jobs[] = [
                'jobId' => (int) $job['jobId'],
                'area' => (string) $job['area'],
                'action' => 'open',
                'nonce' => (string) $job['nonce'],
                'expiresInMs' => (int) $job['expiresInMs'],
            ];
        }

        $now = time();

        return new JsonResponse([
            'serverTime' => date(DATE_ATOM, $now),
            'jobs' => $jobs,
            'nextPollInMs' => empty($jobs) ? 800 : 200,
        ]);
    }

    #[Route('/confirm', name: 'co_device_confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof DeviceApiUser) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        /** @var DeviceApiUser $user */
        $deviceId = $user->getDeviceId();

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $jobId = (int) ($payload['jobId'] ?? 0);
        $nonce = (string) ($payload['nonce'] ?? '');
        $ok = (bool) ($payload['ok'] ?? false);
        $meta = \is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        if ($jobId <= 0 || '' === $nonce) {
            return new JsonResponse(['error' => 'bad_request'], 400);
        }

        $accepted = $this->jobs->confirmJob($deviceId, $jobId, $nonce, $ok, $meta);

        return new JsonResponse(['accepted' => $accepted], 200);
    }
}
