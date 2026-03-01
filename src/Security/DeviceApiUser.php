<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class DeviceApiUser implements UserInterface
{
    /**
     * @param array<string> $areas
     */
    public function __construct(
        private readonly string $deviceId,
        private readonly array $areas,
    ) {
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
