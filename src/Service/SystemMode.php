<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

final class SystemMode
{
    public function __construct(
        private readonly string $mode,
    ) {
    }

    public function isLive(): bool
    {
        return 'live' === $this->mode;
    }

    public function isDemo(): bool
    {
        return 'demo' === $this->mode;
    }

    public function asString(): string
    {
        return $this->mode;
    }
}
