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
    public function __construct(private readonly DoorJobService $jobs) {}

    #[Route('/poll', name: 'co_device_poll', methods: ['POST'])]
    public function poll(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof DeviceApiUser) {
            return new JsonResponse(['success' => false, 'error' => 'unauthorized'], 401);
        }

        $deviceId = $user->getDeviceId();
        $areas = $user->getAreas();

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $limit = (int) ($payload['limit'] ?? 3);

        $claimed = $this->jobs->dispatchJobs($deviceId, $areas, $limit);

        $now = time();
        $window = $this->jobs->getConfirmWindowSeconds();

        $jobs = [];
        foreach ($claimed as $row) {
            $dispatchedAt = (int) ($row['dispatchedAt'] ?? $now);
            $remaining = max(0, ($dispatchedAt + $window) - $now);

            $jobs[] = [
                'jobId' => (int) $row['id'],
                'area' => (string) $row['area'],
                'action' => 'open',
                'nonce' => (string) $row['nonce'],
                'expiresInMs' => $remaining * 1000,
            ];
        }

        return new JsonResponse([
            'success' => true,
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
            return new JsonResponse(['success' => false, 'error' => 'unauthorized'], 401);
        }

        $deviceId = $user->getDeviceId();

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $jobId = (int) ($payload['jobId'] ?? 0);
        $nonce = (string) ($payload['nonce'] ?? '');
        $ok = (bool) ($payload['ok'] ?? false);
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        if ($jobId <= 0 || $nonce === '') {
            return new JsonResponse(['success' => false, 'error' => 'bad_request'], 400);
        }

        $res = $this->jobs->confirmJobResult($deviceId, $jobId, $nonce, $ok, $meta);

        $http = (int) ($res['httpStatus'] ?? 500);

        $body = [
            'success' => (bool) ($res['accepted'] ?? false),
            'accepted' => (bool) ($res['accepted'] ?? false),
        ];

        if (!empty($res['status'])) {
            $body['status'] = (string) $res['status'];
        }
        if (!empty($res['error'])) {
            $body['error'] = (string) $res['error'];
        }
        if (!empty($res['message'])) {
            $body['message'] = (string) $res['message'];
        }

        return new JsonResponse($body, $http);
    }
}
