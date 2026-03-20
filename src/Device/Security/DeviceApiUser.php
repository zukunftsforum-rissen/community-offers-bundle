<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Device\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class DeviceApiUser implements UserInterface
{
    /**
     * @param array<string> $areas
     */
    public function __construct(
        private readonly string $deviceId,
        private readonly array $areas,
        private readonly bool $isEmulator,
    ) {
    }

    public function isEmulator(): bool
    {
        return $this->isEmulator;
    }

    public function getUserIdentifier(): string
    {
        return $this->deviceId;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_DEVICE'];
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * @return array<string>
     */
    public function getAreas(): array
    {
        return $this->areas;
    }

    public function getDeviceId(): string
    {
        return $this->deviceId;
    }
}
