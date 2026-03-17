<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Door;

final class DoorGatewayResult
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly bool $ok,
        private readonly string $status,
        private readonly string $message,
        private readonly int $httpStatus = 200,
        private readonly ?int $jobId = null,
        private readonly ?int $expiresAt = null,
        private readonly ?int $retryAfterSeconds = null,
        private readonly array $context = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function success(
        string $status,
        string $message,
        int $httpStatus = 200,
        ?int $jobId = null,
        ?int $expiresAt = null,
        ?int $retryAfterSeconds = null,
        array $context = [],
    ): self {
        return new self(
            ok: true,
            status: $status,
            message: $message,
            httpStatus: $httpStatus,
            jobId: $jobId,
            expiresAt: $expiresAt,
            retryAfterSeconds: $retryAfterSeconds,
            context: $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function failure(
        string $status,
        string $message,
        int $httpStatus = 500,
        ?int $retryAfterSeconds = null,
        array $context = [],
    ): self {
        return new self(
            ok: false,
            status: $status,
            message: $message,
            httpStatus: $httpStatus,
            jobId: null,
            expiresAt: null,
            retryAfterSeconds: $retryAfterSeconds,
            context: $context,
        );
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getJobId(): ?int
    {
        return $this->jobId;
    }

    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
