<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CommunityOffersBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
