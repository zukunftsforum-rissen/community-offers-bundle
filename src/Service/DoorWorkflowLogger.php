<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

final class DoorWorkflowLogger
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    // =========================================================
    // OPEN PHASE
    // User presses button
    // =========================================================

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
    public function openSuccess(array $context = []): void
    {
        $this->logging->info('door_open.success', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function openForbidden(array $context = []): void
    {
        $this->logging->warning('door_open.forbidden', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function openRateLimited(array $context = []): void
    {
        $this->logging->warning('door_open.rate_limited', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function openError(array $context = []): void
    {
        $this->logging->error('door_open.error', $context);
    }


    // =========================================================
    // JOB PHASE
    // Job lifecycle
    // =========================================================

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
    public function jobMemberLocked(array $context = []): void
    {
        $this->logging->warning('door_job.member_locked', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobAreaLocked(array $context = []): void
    {
        $this->logging->warning('door_job.area_locked', $context);
    }


    // =========================================================
    // DISPATCH PHASE
    // Device polling
    // =========================================================

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
    public function dispatchUnauthorized(array $context = []): void
    {
        $this->logging->warning('door_dispatch.unauthorized', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function dispatchBadJson(array $context = []): void
    {
        $this->logging->warning('door_dispatch.bad_json', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function dispatchBadJsonShape(array $context = []): void
    {
        $this->logging->warning('door_dispatch.bad_json_shape', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function dispatchPollResult(array $context = []): void
    {
        $this->logging->debug('door_dispatch.poll_result', $context);
    }


    // =========================================================
    // CONFIRM PHASE
    // Device confirms execution
    // =========================================================

    /**
     * @param array<string, mixed> $context
     */
    public function confirmRequestReceived(array $context = []): void
    {
        $this->logging->info('door_confirm.request_received', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function confirmAttempt(array $context = []): void
    {
        $this->logging->info('door_confirm.attempt', $context);
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
    public function confirmRateLimited(array $context = []): void
    {
        $this->logging->warning('door_confirm.rate_limited', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function confirmUnauthorized(array $context = []): void
    {
        $this->logging->warning('door_confirm.unauthorized', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function confirmBadJson(array $context = []): void
    {
        $this->logging->warning('door_confirm.bad_json', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function confirmBadJsonShape(array $context = []): void
    {
        $this->logging->warning('door_confirm.bad_json_shape', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function confirmBadRequest(array $context = []): void
    {
        $this->logging->warning('door_confirm.bad_request', $context);
    }


    // =========================================================
    // CONFIRM VALIDATION / EDGE CASES
    // Workflow validation failures
    // =========================================================

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmNotFound(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_not_found', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmIdempotent(array $context = []): void
    {
        $this->logging->info('door_job.confirm_idempotent', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmForbiddenFinalState(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_forbidden_final_state', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmExpired(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_expired', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmInvalidState(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_invalid_state', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmWrongDevice(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_wrong_device', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmWrongNonce(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_wrong_nonce', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmTimeoutConflict(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_timeout_conflict', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmTimeout(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_timeout', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmTransitionBlocked(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_transition_blocked', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function jobConfirmUpdateConflict(array $context = []): void
    {
        $this->logging->warning('door_job.confirm_update_conflict', $context);
    }

}
