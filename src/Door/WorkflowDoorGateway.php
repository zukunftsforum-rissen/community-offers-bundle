<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Door;

use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

final class WorkflowDoorGateway implements DoorGatewayInterface
{
    public const MODE_LIVE = 'live';
    public const MODE_EMULATOR = 'emulator';

    public function __construct(
        private readonly DoorJobService $doorJobs,
        private readonly LoggingService $logging,
    ) {
    }

    public function supports(string $mode): bool
    {
        return \in_array($mode, [
            self::MODE_LIVE,
            self::MODE_EMULATOR,
        ], true);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function open(string $area, int $memberId, array $context = []): DoorGatewayResult
    {
        $mode = isset($context['mode']) ? (string) $context['mode'] : self::MODE_LIVE;
        $channel = match ($mode) {
            self::MODE_LIVE => 'physical',
            self::MODE_EMULATOR => 'emulator',
            default => throw new \RuntimeException(sprintf('Unsupported mode "%s"', $mode)),
        };

        $ip = isset($context['ip']) ? (string) $context['ip'] : '';
        $userAgent = isset($context['userAgent']) ? (string) $context['userAgent'] : '';
        $correlationId = isset($context['correlationId']) ? (string) $context['correlationId'] : '';

        $this->logging->initiateLogging('door', 'community-offers');
        $this->logging->info('door.gateway.workflow_open_requested', [
            'area' => $area,
            'memberId' => $memberId,
            'ip' => $ip,
            'correlationId' => $correlationId,
        ]);

        $this->doorJobs->expireOldJobs();

        /** @var array{
         *   ok?: bool,
         *   accepted?: bool,
         *   message?: string,
         *   jobId?: int,
         *   status?: string,
         *   expiresAt?: int,
         *   retryAfterSeconds?: int,
         *   httpStatus?: int
         * } $result
         */
        $result = $this->doorJobs->createOpenJob(
            memberId: $memberId,
            area: $area,
            ip: $ip,
            userAgent: $userAgent,
            correlationId: $correlationId,
        );

        $httpStatus = isset($result['httpStatus']) ? (int) $result['httpStatus'] : 202;
        $status = isset($result['status']) ? (string) $result['status'] : 'queued';
        $message = isset($result['message']) ? (string) $result['message'] : 'Door open requested.';
        $jobId = isset($result['jobId']) ? (int) $result['jobId'] : null;
        $expiresAt = isset($result['expiresAt']) ? (int) $result['expiresAt'] : null;
        $retryAfterSeconds = isset($result['retryAfterSeconds']) ? (int) $result['retryAfterSeconds'] : null;
        $ok = isset($result['ok']) ? (bool) $result['ok'] : (null !== $jobId);

        if ($ok) {
            return DoorGatewayResult::success(
                status: $status,
                message: $message,
                httpStatus: $httpStatus,
                jobId: $jobId,
                expiresAt: $expiresAt,
                retryAfterSeconds: $retryAfterSeconds,
                context: [
                    'area' => $area,
                    'memberId' => $memberId,
                    'mode' => $mode,
                    'channel' => $channel,
                    'accepted' => (bool) ($result['accepted'] ?? (null !== $jobId)),
                ] + $context,
            );
        }

        return DoorGatewayResult::failure(
            status: $status,
            message: $message,
            httpStatus: $httpStatus,
            retryAfterSeconds: $retryAfterSeconds,
            context: [
                'area' => $area,
                'memberId' => $memberId,
                'mode' => $mode,
                'channel' => $channel,
                'accepted' => false,
            ] + $context,
        );
    }
}
