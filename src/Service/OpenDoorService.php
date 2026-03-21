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
        private readonly DoorOpenObserverResolver $observerResolver,
        private readonly SystemMode $systemMode,
    ) {
    }

    /**
     * @return array{
     *   ok: bool,
     *   httpStatus: int,
     *   message: string,
     *   accepted: bool,
     *   mode: string,
     *   jobId?: int,
     *   status?: string,
     *   expiresAt?: int,
     *   retryAfterSeconds?: int
     * }
     */
    public function open(int $memberId, string $area, string $ip = '', string $userAgent = '', string $correlationId = ''): array
    {
        $mode = $this->systemMode->asString();
        $observer = $this->observerResolver->resolve($mode);

        $areas = $this->accessService->getGrantedAreasForMemberId($memberId);

        if (!\in_array($area, $areas, true)) {
            $observer->onForbidden(
                memberId: $memberId,
                area: $area,
                ip: $ip,
                correlationId: $correlationId,
                mode: $mode,
            );

            return [
                'ok' => false,
                'httpStatus' => 403,
                'message' => 'Forbidden',
                'accepted' => false,
                'mode' => $mode,
            ];
        }

        $gateway = $this->doorGatewayResolver->resolve($mode);

        $result = $gateway->open(
            $area,
            $memberId,
            [
                'ip' => $ip,
                'userAgent' => $userAgent,
                'correlationId' => $correlationId,
                'mode' => $mode,
            ],
        );

        $observer->onResult(
            memberId: $memberId,
            area: $area,
            ip: $ip,
            userAgent: $userAgent,
            correlationId: $correlationId,
            mode: $mode,
            result: $result,
        );

        return $this->toResponsePayload($result, $mode);
    }

    /**
     * @return array{
     *   ok: bool,
     *   httpStatus: int,
     *   message: string,
     *   accepted: bool,
     *   mode: string,
     *   jobId?: int,
     *   status?: string,
     *   expiresAt?: int,
     *   retryAfterSeconds?: int
     * }
     */
    private function toResponsePayload(DoorGatewayResult $result, string $mode): array
    {
        $payload = [
            'ok' => $result->isOk(),
            'httpStatus' => $result->getHttpStatus(),
            'message' => $result->getMessage(),
            'accepted' => $result->isOk() && null !== $result->getJobId(),
            'mode' => $mode,
            'status' => $result->getStatus(),
        ];

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
