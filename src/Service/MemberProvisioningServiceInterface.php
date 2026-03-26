<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

interface MemberProvisioningServiceInterface
{
    public function createMemberFromConfirmedRequest(int $requestId): MemberProvisioningResult;
}
