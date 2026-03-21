<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

final class SystemMode
{
    public function __construct(private readonly string $mode)
    {
        if (!\in_array($mode, ['live', 'emulator'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid system mode "%s"', $mode));
        }
    }

    public function asString(): string
    {
        return $this->mode;
    }

    public function isLive(): bool
    {
        return 'live' === $this->mode;
    }

    public function isEmulator(): bool
    {
        return 'emulator' === $this->mode;
    }

    public function isValid(): bool
    {
        return \in_array($this->mode, ['live', 'emulator'], true);
    }
}
