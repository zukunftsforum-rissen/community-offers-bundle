<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Symfony\Component\Uid\Ulid;

final class CorrelationIdService
{
    public function create(): string
    {
        $ulid = (string) new Ulid(); // 26 chars
        $random = bin2hex(random_bytes(19)); // 38 chars

        return $ulid.$random; // 64 total
    }
}
