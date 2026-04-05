<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

final class DoorWorkflowLogger
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function openAttempt(array $context = []): void
    {
        $this->logging->info('door_open.attempt', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobCreated(array $context = []): void
    {
        $this->logging->info('door_job.created', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobReused(array $context = []): void
    {
        $this->logging->info('door_job.reused', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobDispatchFailed(array $context = []): void
    {
        $this->logging->error('door_job.dispatch_failed', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobMaxAttemptsReached(array $context = []): void
    {
        $this->logging->warning(
            'door_job.max_attempts_reached',
            $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function dispatchDispatched(array $context = []): void
    {
        $this->logging->info(
            'door_dispatch.dispatched',
            $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function confirmConfirmed(array $context = []): void
    {
        $this->logging->info(
            'door_confirm.confirmed',
            $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function openSuccess(array $context = []): void
    {
        $this->logging->info(
            'door_open.success',
            $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function openForbidden(array $context = []): void
    {
        $this->logging->warning(
            'door_open.forbidden',
            $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function openRateLimited(array $context = []): void
    {
        $this->logging->warning(
            'door_open.rate_limited',
            $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function openError(array $context = []): void
    {
        $this->logging->error(
            'door_open.error',
            $context,
        );
    }
}
