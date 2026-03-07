<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Workflow;

final class DoorJob
{
    public function __construct(
        private int $id,
        private string $area,
        private string $status = 'pending',
        private \DateTimeImmutable|null $dispatchedAt = null,
        private \DateTimeImmutable|null $confirmExpiresAt = null,
        private string|null $deviceId = null,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getArea(): string
    {
        return $this->area;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getDispatchedAt(): \DateTimeImmutable|null
    {
        return $this->dispatchedAt;
    }

    public function setDispatchedAt(\DateTimeImmutable|null $dt): void
    {
        $this->dispatchedAt = $dt;
    }

    public function getConfirmExpiresAt(): \DateTimeImmutable|null
    {
        return $this->confirmExpiresAt;
    }

    public function setConfirmExpiresAt(\DateTimeImmutable|null $dt): void
    {
        $this->confirmExpiresAt = $dt;
    }

    public function getDeviceId(): string|null
    {
        return $this->deviceId;
    }

    public function setDeviceId(string|null $id): void
    {
        $this->deviceId = $id;
    }
}
