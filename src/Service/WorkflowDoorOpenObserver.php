<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayResult;

final class WorkflowDoorOpenObserver implements DoorOpenObserverInterface
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly DoorAuditLogger $audit,
    ) {
    }

    public function onForbidden(int $memberId, string $area, string $ip, string $correlationId, string $mode): void
    {
        $this->logging->warning('door_open.forbidden', [
            'cid' => $correlationId,
            'memberId' => $memberId,
            'area' => $area,
            'ip' => $ip,
            'mode' => $mode,
        ]);

        $this->audit->audit(
            action: 'door_open',
            area: $area,
            result: 'forbidden',
            message: 'Door open forbidden',
            correlationId: $correlationId,
            memberId: $memberId,
            context: [
                'ip' => $ip,
                'mode' => $mode,
            ],
        );
    }

    public function onResult(int $memberId, string $area, string $ip, string $userAgent, string $correlationId, string $mode, DoorGatewayResult $result): void
    {
        $auditResult = $result->isOk() ? $result->getStatus() : 'error';

        $this->audit->audit(
            action: 'door_open',
            area: $area,
            result: $auditResult,
            message: $result->getMessage(),
            correlationId: $correlationId,
            memberId: $memberId,
            context: [
                'ip' => $ip,
                'userAgent' => $userAgent,
                'mode' => $mode,
            ] + $result->getContext(),
        );

        if ($result->isOk()) {
            $this->logging->info('door_open.success', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'jobId' => $result->getJobId(),
                'status' => $result->getStatus(),
                'httpStatus' => $result->getHttpStatus(),
                'mode' => $mode,
            ]);
        } elseif (429 === $result->getHttpStatus()) {
            $this->logging->warning('door_open.rate_limited', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'retryAfterSeconds' => $result->getRetryAfterSeconds(),
                'httpStatus' => $result->getHttpStatus(),
                'mode' => $mode,
            ]);
        } else {
            $this->logging->error('door_open.error', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'httpStatus' => $result->getHttpStatus(),
                'message' => $result->getMessage(),
                'jobId' => $result->getJobId(),
                'status' => $result->getStatus(),
                'mode' => $mode,
            ]);
        }
    }
}
