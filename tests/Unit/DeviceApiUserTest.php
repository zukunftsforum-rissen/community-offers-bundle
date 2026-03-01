<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Security\DeviceApiUser;

class DeviceApiUserTest extends TestCase
{
    /**
     * Verifies identifier, device ID, role, and area accessors expose constructor values.
     */
    public function testExposesIdentifierRolesAndAreasWhenConstructed(): void
    {
        $user = new DeviceApiUser('device-42', ['workshop', 'depot']);

        $this->assertSame('device-42', $user->getUserIdentifier());
        $this->assertSame('device-42', $user->getDeviceId());
        $this->assertSame(['ROLE_DEVICE'], $user->getRoles());
        $this->assertSame(['workshop', 'depot'], $user->getAreas());
    }

    /**
     * Verifies eraseCredentials is a no-op for this stateless user type.
     */
    public function testKeepsIdentifierWhenEraseCredentialsIsCalled(): void
    {
        $user = new DeviceApiUser('device-42', ['workshop']);

        $user->eraseCredentials();

        $this->assertSame('device-42', $user->getUserIdentifier());
    }
}
