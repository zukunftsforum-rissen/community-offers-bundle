<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\MemberModel;

class MemberProvisioningResult
{
    public function __construct(
        public readonly MemberModel $member,
        public readonly bool $createdNewMember,
    ) {
    }
}
