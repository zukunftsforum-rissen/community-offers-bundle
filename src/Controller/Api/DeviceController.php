<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Security\DeviceApiUser;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Service\DeviceAccessPolicy;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Service\DeviceConfirmRateLimitService;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Service\DeviceHeartbeatInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Service\DeviceRateLimitService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

#[Route('/api/device')]
final class DeviceController extends AbstractController
{
    public function __construct(
        private readonly DoorJobService $jobs,
        private readonly LoggingService $logging,
        private readonly DeviceHeartbeatInterface $deviceHeartbeatService,
        private readonly DeviceRateLimitService $deviceRateLimitService,
        private readonly DeviceConfirmRateLimitService $deviceConfirmRateLimitService,
        private readonly DeviceAccessPolicy $deviceAccessPolicy,
    ) {
    }

    #[Route('/poll', name: 'co_device_poll', methods: ['POST'])]
    public function poll(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof DeviceApiUser) {
            $this->logging->warning('door_dispatch.unauthorized', [
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        if (!$this->deviceAccessPolicy->canPoll($user)) {
            $this->logging->warning('device_access.denied', [
                'deviceId' => $user->getDeviceId(),
                'isEmulator' => $user->isEmulator(),
                'reason' => $this->deviceAccessPolicy->denialReason($user),
            ]);

            return new JsonResponse(
                ['error' => $this->deviceAccessPolicy->denialReason($user)],
                $this->deviceAccessPolicy->denialStatusCode(),
            );
        }

        $deviceId = $user->getDeviceId();
        $areas = $user->getAreas();

        if (!$this->deviceRateLimitService->isPollAllowed($deviceId, 2)) {
            $this->logging->warning('device_poll.rate_limited', [
                'deviceId' => $deviceId,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(
                [
                    'error' => 'rate_limit_exceeded',
                ],
                429,
            );
        }

        $this->jobs->expireOldJobs();

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logging->warning('door_dispatch.bad_json', [
                'deviceId' => $deviceId,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'bad_request'], 400);
        }

        if (!\is_array($payload)) {
            $this->logging->warning('door_dispatch.bad_json_shape', [
                'deviceId' => $deviceId,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'bad_request'], 400);
        }

        $this->deviceHeartbeatService->registerPoll($deviceId, $areas);

        $limit = (int) ($payload['limit'] ?? 3);
        $limit = max(1, min(10, $limit));

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

        $this->logging->debug('door_dispatch.poll_result', [
            'deviceId' => $deviceId,
            'areas' => $areas,
            'limit' => $limit,
            'jobsReturned' => \count($jobs),
            'jobIds' => array_map(static fn (array $job): int => (int) $job['jobId'], $jobs),
            'correlationIds' => array_values(array_filter(array_map(
                static fn (array $job): string => (string) $job['correlationId'],
                $jobs,
            ))),
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
        $user = $this->getUser();
        if (!$user instanceof DeviceApiUser) {
            $this->logging->warning('door_confirm.unauthorized', [
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        $isEmulator = $user->isEmulator();

        if (!$this->deviceAccessPolicy->canConfirm($user)) {
            $this->logging->warning('device_access.denied', [
                'deviceId' => $user->getDeviceId(),
                'isEmulator' => $isEmulator,
                'reason' => $this->deviceAccessPolicy->denialReason($user),
            ]);

            return new JsonResponse(
                ['error' => $this->deviceAccessPolicy->denialReason($user)],
                $this->deviceAccessPolicy->denialStatusCode(),
            );
        }

        $deviceId = $user->getDeviceId();

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logging->warning('door_confirm.bad_json', [
                'deviceId' => $deviceId,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'bad_request'], 400);
        }

        if (!\is_array($payload)) {
            $this->logging->warning('door_confirm.bad_json_shape', [
                'deviceId' => $deviceId,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'bad_request'], 400);
        }

        $jobId = (int) ($payload['jobId'] ?? 0);
        $nonce = (string) ($payload['nonce'] ?? '');
        $ok = (bool) ($payload['ok'] ?? false);
        $meta = \is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $requestCid = (string) ($payload['correlationId'] ?? ($meta['correlationId'] ?? ''));

        if ('' !== $requestCid && !isset($meta['correlationId'])) {
            $meta['correlationId'] = $requestCid;
        }

        if (
            $jobId <= 0
            || '' === $nonce
            || 64 !== \strlen($nonce)
            || !preg_match('/^[a-f0-9]{64}$/', $nonce)
        ) {
            $this->logging->warning('door_confirm.bad_request', [
                'deviceId' => $deviceId,
                'jobId' => $jobId,
                'requestCid' => $requestCid,
            ]);

            return new JsonResponse(['error' => 'bad_request'], 400);
        }

        $this->logging->info('door_confirm.request_received', [
            'deviceId' => $deviceId,
            'jobId' => $jobId,
            'requestCid' => $requestCid,
            'ok' => $ok,
        ]);

        if (!$this->deviceConfirmRateLimitService->isAllowed($deviceId)) {
            $this->logging->warning('door_confirm.rate_limited', [
                'deviceId' => $deviceId,
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'rate_limit_exceeded'], 429);
        }

        $result = $this->jobs->confirmJobDetailed($deviceId, $jobId, $nonce, $ok, $meta);

        if ((bool) $result['accepted']) {
            $this->deviceConfirmRateLimitService->reset($deviceId);
        } else {
            $this->deviceConfirmRateLimitService->registerFailure($deviceId);
        }

        $level = (bool) $result['accepted'] ? 'info' : 'warning';
        $this->logging->{$level}('door_confirm.result', [
            'deviceId' => $deviceId,
            'jobId' => $jobId,
            'requestCid' => $requestCid,
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

    #[Route('/whoami', name: 'co_device_whoami', methods: ['GET'])]
    public function whoami(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof DeviceApiUser) {
            $this->logging->warning('device_auth.whoami_unauthorized', [
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(
                [
                    'authenticated' => false,
                    'deviceId' => null,
                    'areas' => [],
                    'isEmulator' => false,
                ],
                401,
            );
        }

        return new JsonResponse([
            'authenticated' => true,
            'deviceId' => $user->getDeviceId(),
            'areas' => $user->getAreas(),
            'isEmulator' => $user->isEmulator(),
        ]);
    }
}
