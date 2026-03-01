<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\Model\Registry;
use Contao\Model;
use Contao\MemberModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;

class AccessServiceTest extends TestCase
{
    /**
     * Verifies known areas are exposed as configured string keys.
     */
    public function testGetKnownAreasReturnsConfiguredKeysAsStrings(): void
    {
        $service = $this->createService([
            'workshop' => 1,
            'sharing' => 2,
            'depot' => 3,
        ]);

        $this->assertSame(['workshop', 'sharing', 'depot'], $service->getKnownAreas());
    }

    /**
     * Verifies area-to-group mapping returns sorted unique IDs and ignores unknown areas.
     */
    public function testGetGroupIdsForAreasReturnsSortedUniqueIds(): void
    {
        $service = $this->createService([
            'workshop' => 4,
            'sharing' => 2,
            'depot' => 4,
            'swap-house' => 7,
        ]);

        $result = $service->getGroupIdsForAreas(['swap-house', 'unknown', 'sharing', 'workshop', 'depot']);

        $this->assertSame([2, 4, 7], $result);
    }

    /**
     * Verifies unknown areas map to an empty group ID list.
     */
    public function testGetGroupIdsForAreasReturnsEmptyListForUnknownAreas(): void
    {
        $service = $this->createService([
            'workshop' => 1,
            'sharing' => 2,
        ]);

        $this->assertSame([], $service->getGroupIdsForAreas(['not-found']));
    }

    /**
     * Verifies member groups that do not match configured area groups return no granted areas.
     */
    public function testGetGrantedAreasForMemberIdReturnsEmptyListWhenMemberHasNoMatchingGroups(): void
    {
        $memberId = 123456;
        $previousModels = $GLOBALS['TL_MODELS'] ?? null;
        $GLOBALS['TL_MODELS']['tl_member'] = MemberModel::class;

        Registry::getInstance()->reset();

        $member = new class($memberId, serialize(['11', 12])) extends Model {
            protected static $strTable = 'tl_member';

            public function __construct(int $id, string $groups)
            {
                $this->arrData = [
                    'id' => $id,
                    'groups' => $groups,
                ];
            }

            public function onRegister(Registry $registry): void
            {
            }

            public function onUnregister(Registry $registry): void
            {
            }
        };
        $member->attach();

        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $doorGateway = $this->createStub(DoorGatewayInterface::class);

        $service = new AccessService($framework, [
            'workshop' => 2,
            'depot' => 7,
        ], $doorGateway);

        $result = $service->getGrantedAreasForMemberId($memberId);

        $this->assertSame([], $result);

        Registry::getInstance()->reset();

        if (null === $previousModels) {
            unset($GLOBALS['TL_MODELS']);
        } else {
            $GLOBALS['TL_MODELS'] = $previousModels;
        }
    }

    /**
     * Verifies configured area keys are returned for matching member group IDs.
     */
    public function testGetGrantedAreasForMemberIdReturnsMappedAreasFromMemberGroups(): void
    {
        $memberId = 987654;
        $previousModels = $GLOBALS['TL_MODELS'] ?? null;
        $GLOBALS['TL_MODELS']['tl_member'] = MemberModel::class;

        Registry::getInstance()->reset();

        $member = new class($memberId, serialize(['2', 7, '999'])) extends Model {
            protected static $strTable = 'tl_member';

            public function __construct(int $id, string $groups)
            {
                $this->arrData = [
                    'id' => $id,
                    'groups' => $groups,
                ];
            }

            public function onRegister(Registry $registry): void
            {
            }

            public function onUnregister(Registry $registry): void
            {
            }
        };
        $member->attach();

        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $doorGateway = $this->createStub(DoorGatewayInterface::class);

        $service = new AccessService($framework, [
            'workshop' => 2,
            'sharing' => 4,
            'depot' => 7,
        ], $doorGateway);

        $result = $service->getGrantedAreasForMemberId($memberId);

        $this->assertSame(['workshop', 'depot'], $result);

        Registry::getInstance()->reset();

        if (null === $previousModels) {
            unset($GLOBALS['TL_MODELS']);
        } else {
            $GLOBALS['TL_MODELS'] = $previousModels;
        }
    }

    /**
     * @param array<string, int> $areaGroups
     */
    private function createService(array $areaGroups): AccessService
    {
        $framework = $this->createStub(ContaoFramework::class);
        $doorGateway = $this->createStub(DoorGatewayInterface::class);

        return new AccessService($framework, $areaGroups, $doorGateway);
    }
}
