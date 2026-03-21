<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Security\DeviceApiUser;

class DeviceApiUserTest extends TestCase
{
    /**
     * Verifies identifier, device ID, role, and area accessors expose constructor values.
     */
    public function testExposesIdentifierRolesAndAreasWhenConstructed(): void
    {
        $user = new DeviceApiUser('device-42', ['workshop', 'depot'], false);

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
        $user = new DeviceApiUser('device-42', ['workshop'], false);

        $user->eraseCredentials();

        $this->assertSame('device-42', $user->getUserIdentifier());
    }
    /**
     * Verifies isEmulator flag is exposed correctly from the constructor.
     */
    public function testIsEmulatorFlagIsExposedFromConstructor(): void
    {
        $emulator = new DeviceApiUser('emu-1', ['depot'], true);
        $physical = new DeviceApiUser('phys-1', ['depot'], false);

        $this->assertTrue($emulator->isEmulator());
        $this->assertFalse($physical->isEmulator());
    }
}
