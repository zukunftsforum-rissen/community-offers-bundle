<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Security\DeviceApiUser;
use ZukunftsforumRissen\CommunityOffersBundle\Device\Service\DeviceAccessPolicy;
use ZukunftsforumRissen\CommunityOffersBundle\Service\SystemMode;

class DeviceAccessPolicyTest extends TestCase
{
    private function makePhysical(): DeviceApiUser
    {
        return new DeviceApiUser('device-physical', ['depot'], false);
    }

    private function makeEmulator(): DeviceApiUser
    {
        return new DeviceApiUser('device-emulator', ['depot'], true);
    }

    // --- canPoll ---

    /**
     * Physical devices are allowed to poll in live mode.
     */
    public function testCanPollAllowsPhysicalDeviceInLiveMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('live'));

        $this->assertTrue($policy->canPoll($this->makePhysical()));
    }

    /**
     * Emulator devices must not be allowed to poll in live mode.
     */
    public function testCanPollDeniesEmulatorDeviceInLiveMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('live'));

        $this->assertFalse($policy->canPoll($this->makeEmulator()));
    }

    /**
     * Emulator devices are allowed to poll when system mode is emulator.
     */
    public function testCanPollAllowsEmulatorDeviceInEmulatorMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('emulator'));

        $this->assertTrue($policy->canPoll($this->makeEmulator()));
    }

    /**
     * Physical devices must not poll when system is running in emulator mode.
     */
    public function testCanPollDeniesPhysicalDeviceInEmulatorMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('emulator'));

        $this->assertFalse($policy->canPoll($this->makePhysical()));
    }

    // --- canConfirm mirrors canPoll ---

    /**
     * canConfirm grants same access as canPoll for physical device in live mode.
     */
    public function testCanConfirmAllowsPhysicalDeviceInLiveMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('live'));

        $this->assertTrue($policy->canConfirm($this->makePhysical()));
    }

    /**
     * canConfirm denies same access as canPoll for emulator in live mode.
     */
    public function testCanConfirmDeniesEmulatorDeviceInLiveMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('live'));

        $this->assertFalse($policy->canConfirm($this->makeEmulator()));
    }

    /**
     * canConfirm grants same access as canPoll for emulator in emulator mode.
     */
    public function testCanConfirmAllowsEmulatorDeviceInEmulatorMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('emulator'));

        $this->assertTrue($policy->canConfirm($this->makeEmulator()));
    }

    /**
     * canConfirm denies same access as canPoll for physical device in emulator mode.
     */
    public function testCanConfirmDeniesPhysicalDeviceInEmulatorMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('emulator'));

        $this->assertFalse($policy->canConfirm($this->makePhysical()));
    }

    // --- denialReason ---

    /**
     * Emulator device denied in live mode gets a specific reason string.
     */
    public function testDenialReasonForEmulatorInLiveMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('live'));

        $this->assertSame(
            'emulator_not_allowed_in_live',
            $policy->denialReason($this->makeEmulator()),
        );
    }

    /**
     * Physical device denied in emulator mode gets a specific reason string.
     */
    public function testDenialReasonForPhysicalDeviceInEmulatorMode(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('emulator'));

        $this->assertSame(
            'physical_device_not_allowed_in_emulator',
            $policy->denialReason($this->makePhysical()),
        );
    }

    /**
     * When no specific denial condition matches the fallback reason is 'forbidden'.
     */
    public function testDenialReasonFallbackReturnsForbidden(): void
    {
        // Physical device in live mode is allowed, so the fallback denial reason
        // ('forbidden') is what would be returned if asked out of context.
        $policy = new DeviceAccessPolicy(new SystemMode('live'));

        $this->assertSame('forbidden', $policy->denialReason($this->makePhysical()));
    }

    // --- denialStatusCode ---

    /**
     * Access denial always produces HTTP 403.
     */
    public function testDenialStatusCodeIs403(): void
    {
        $policy = new DeviceAccessPolicy(new SystemMode('live'));

        $this->assertSame(403, $policy->denialStatusCode());
    }
}
