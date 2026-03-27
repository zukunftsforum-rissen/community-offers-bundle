<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\StringUtil;

class AccessService
{
    /**
     * @param array<string, int> $areaGroups
     */
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly array $areaGroups,
    ) {
        if (!$this->areaGroups) {
            throw new \LogicException('community_offers.area_groups must not be empty');
        }
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

        $groups = StringUtil::deserialize($member->groups, true);

        /** @var list<int|string> $groups */
        $groups = array_map('intval', $groups);

        // Performance: schneller Lookup
        $groupsLookup = array_flip($groups);

        $areas = [];

        foreach ($this->areaGroups as $area => $groupId) {
            if (isset($groupsLookup[(int) $groupId])) {
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
        return array_keys($this->areaGroups);
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
            if (!isset($this->areaGroups[$area])) {
                throw new \InvalidArgumentException(\sprintf('Unknown area "%s"', $area));
            }

            $ids[] = (int) $this->areaGroups[$area];
        }

        $ids = array_values(array_unique($ids));

        sort($ids);

        return $ids;
    }
}
