<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\StringUtil;
use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayInterface;

class AccessService
{
    /**
     * @param array<string, int> $areaGroups
     */
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly array $areaGroups,
        private readonly DoorGatewayInterface $doorGateway,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getGrantedAreasForMemberId(int $memberId): array
    {
        $this->framework->initialize();

        $member = MemberModel::findById($memberId);
        if (null === $member) {
            return [];
        }

        $groups = StringUtil::deserialize($member->groups, true); // list<int|string>
        $groups = array_map('intval', $groups);

        $areas = [];

        foreach ($this->areaGroups as $area => $groupId) {
            if (\in_array((int) $groupId, $groups, true)) {
                $areas[] = (string) $area;
            }
        }

        return $areas;
    }

    /**
     * @return list<string>
     */
    public function getKnownAreas(): array
    {
        return array_values(array_map('strval', array_keys($this->areaGroups)));
    }

    /**
     * @param list<string> $areas
     *
     * @return list<int>
     */
    public function getGroupIdsForAreas(array $areas): array
    {
        $ids = [];

        foreach ($areas as $area) {
            if (isset($this->areaGroups[$area])) {
                $ids[] = (int) $this->areaGroups[$area];
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
