<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use ZukunftsforumRissen\CommunityOffersBundle\Security\DeviceApiUser;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

#[Route('/api/device')]
final class DeviceController extends AbstractController
{
    public function __construct(
        private readonly DoorJobService $jobs,
        private readonly LoggingService $logging,
    ) {
    }

    #[Route('/poll', name: 'co_device_poll', methods: ['POST'])]
    public function poll(Request $request): JsonResponse
    {
        $this->logging->initiateLogging('door', 'community-offers');

        $user = $this->getUser();
        if (!$user instanceof DeviceApiUser) {
            $this->logging->warning('device.poll.unauthorized', [
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $deviceId = $user->getDeviceId();
        $areas = $user->getAreas();

        $this->jobs->expireOldJobs();

        $payload = json_decode((string) $request->getContent(), true);
        $limit = (int) ($payload['limit'] ?? 3);
        $claimed = $this->jobs->dispatchJobs($deviceId, $areas, $limit);

        $jobs = [];

        foreach ($claimed as $job) {
            $jobs[] = [
                'jobId' => (int) $job['jobId'],
                'area' => (string) $job['area'],
                'action' => 'open',
                'nonce' => (string) $job['nonce'],
                'expiresInMs' => (int) $job['expiresInMs'],
                'correlationId' => (string) $job['correlationId'],
            ];
        }

        $this->logging->info('device.poll.result', [
            'deviceId' => $deviceId,
            'areas' => $areas,
            'limit' => $limit,
            'jobsReturned' => \count($jobs),
        ]);

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
        $this->logging->initiateLogging('door', 'community-offers');

        $user = $this->getUser();
        if (!$user instanceof DeviceApiUser) {
            $this->logging->warning('device.confirm.unauthorized', [
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $deviceId = $user->getDeviceId();

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $jobId = (int) ($payload['jobId'] ?? 0);
        $nonce = (string) ($payload['nonce'] ?? '');
        $ok = (bool) ($payload['ok'] ?? false);
        $meta = \is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        if ($jobId <= 0 || '' === $nonce) {
            $this->logging->warning('device.confirm.bad_request', [
                'deviceId' => $deviceId,
                'jobId' => $jobId,
            ]);

            return new JsonResponse(['error' => 'bad_request'], 400);
        }

        $result = $this->jobs->confirmJobDetailed($deviceId, $jobId, $nonce, $ok, $meta);

        $this->logging->info('device.confirm.result', [
            'deviceId' => $deviceId,
            'jobId' => $jobId,
            'accepted' => (bool) $result['accepted'],
            'httpStatus' => (int) $result['httpStatus'],
            'status' => $result['status'] ?? null,
            'error' => $result['error'] ?? null,
        ]);

        return new JsonResponse(
            [
                'accepted' => (bool) $result['accepted'],
                'status' => $result['status'] ?? null,
                'error' => $result['error'] ?? null,
            ],
            (int) $result['httpStatus'],
        );
    }
}
