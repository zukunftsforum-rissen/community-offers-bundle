<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Device\Service;

use ZukunftsforumRissen\CommunityOffersBundle\Device\Security\DeviceApiUser;
use ZukunftsforumRissen\CommunityOffersBundle\Service\SystemMode;

final class DeviceAccessPolicy
{
    public function __construct(
        private readonly SystemMode $mode,
    ) {
    }

    public function canPoll(DeviceApiUser $device): bool
    {
        if ($this->mode->isLive()) {
            return !$device->isEmulator();
        }

        if ($this->mode->isEmulator()) {
            return $device->isEmulator();
        }

        return false;
    }

    public function canConfirm(DeviceApiUser $device): bool
    {
        return $this->canPoll($device);
    }

    public function denialReason(DeviceApiUser $device): string
    {
        if ($this->mode->isLive() && $device->isEmulator()) {
            return 'emulator_not_allowed_in_live';
        }

        if ($this->mode->isEmulator() && !$device->isEmulator()) {
            return 'physical_device_not_allowed_in_emulator';
        }

        return 'forbidden';
    }

    public function denialStatusCode(): int
    {
        return 403;
    }
}
