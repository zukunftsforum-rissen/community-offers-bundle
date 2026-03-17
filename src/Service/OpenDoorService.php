<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayResolver;
use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayResult;

final class OpenDoorService
{
    public function __construct(
        private readonly AccessService $accessService,
        private readonly DoorGatewayResolver $doorGatewayResolver,
        private readonly LoggingService $logging,
        private readonly DoorAuditLogger $audit,
        private readonly string $mode = 'live',
    ) {
    }

    /**
     * @return array{
     *   ok: bool,
     *   httpStatus: int,
     *   message: string,
     *   accepted: bool,
     *   mode: string,
     *   simulated?: bool,
     *   jobId?: int,
     *   status?: string,
     *   expiresAt?: int,
     *   retryAfterSeconds?: int
     * }
     */
    public function open(
        int $memberId,
        string $area,
        string $ip = '',
        string $userAgent = '',
        string $correlationId = '',
    ): array {
        $areas = $this->accessService->getGrantedAreasForMemberId($memberId);

        if (!\in_array($area, $areas, true)) {
            $this->logging->warning('door_open.forbidden', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'ip' => $ip,
                'mode' => $this->mode,
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
                    'mode' => $this->mode,
                ],
            );

            return [
                'ok' => false,
                'httpStatus' => 403,
                'message' => 'Forbidden',
                'accepted' => false,
                'mode' => $this->mode,
            ];
        }

        $gateway = $this->doorGatewayResolver->resolve($this->mode);

        $gatewayResult = $gateway->open($area, $memberId, [
            'ip' => $ip,
            'userAgent' => $userAgent,
            'correlationId' => $correlationId,
        ]);

        $auditResult = $gatewayResult->isOk() ? $gatewayResult->getStatus() : 'error';

        $this->audit->audit(
            action: 'door_open',
            area: $area,
            result: $auditResult,
            message: $gatewayResult->getMessage(),
            correlationId: $correlationId,
            memberId: $memberId,
            context: [
                'ip' => $ip,
                'userAgent' => $userAgent,
                'mode' => $this->mode,
            ] + $gatewayResult->getContext(),
        );

        if ($gatewayResult->isOk()) {
            $this->logging->info('door_open.success', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'jobId' => $gatewayResult->getJobId(),
                'status' => $gatewayResult->getStatus(),
                'httpStatus' => $gatewayResult->getHttpStatus(),
                'mode' => $this->mode,
            ]);
        } elseif (429 === $gatewayResult->getHttpStatus()) {
            $this->logging->warning('door_open.rate_limited', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'retryAfterSeconds' => $gatewayResult->getRetryAfterSeconds(),
                'httpStatus' => $gatewayResult->getHttpStatus(),
                'mode' => $this->mode,
            ]);
        } else {
            $this->logging->error('door_open.error', [
                'cid' => $correlationId,
                'memberId' => $memberId,
                'area' => $area,
                'httpStatus' => $gatewayResult->getHttpStatus(),
                'message' => $gatewayResult->getMessage(),
                'jobId' => $gatewayResult->getJobId(),
                'status' => $gatewayResult->getStatus(),
                'mode' => $this->mode,
            ]);
        }

        return $this->toResponsePayload($gatewayResult);
    }

    /**
     * @return array{
     *   ok: bool,
     *   httpStatus: int,
     *   message: string,
     *   accepted: bool,
     *   mode: string,
     *   simulated?: bool,
     *   jobId?: int,
     *   status?: string,
     *   expiresAt?: int,
     *   retryAfterSeconds?: int
     * }
     */
    private function toResponsePayload(DoorGatewayResult $result): array
    {
        $payload = [
            'ok' => $result->isOk(),
            'httpStatus' => $result->getHttpStatus(),
            'message' => $result->getMessage(),
            'accepted' => $result->isOk() && (null !== $result->getJobId() || 'simulation' === $this->mode),
            'mode' => $this->mode,
            'status' => $result->getStatus(),
        ];

        if ('simulation' === $this->mode) {
            $payload['simulated'] = true;
        }

        if (null !== $result->getJobId()) {
            $payload['jobId'] = $result->getJobId();
        }

        if (null !== $result->getExpiresAt()) {
            $payload['expiresAt'] = $result->getExpiresAt();
        }

        if (null !== $result->getRetryAfterSeconds()) {
            $payload['retryAfterSeconds'] = $result->getRetryAfterSeconds();
        }

        return $payload;
    }
}
