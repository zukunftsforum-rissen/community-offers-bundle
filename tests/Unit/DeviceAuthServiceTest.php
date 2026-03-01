<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DeviceAuthService;

class DeviceAuthServiceTest extends TestCase
{
    /**
     * Verifies missing tokens are rejected without any database lookup.
     */
    public function testAuthenticateReturnsNullWithoutToken(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects($this->never())->method('fetchAssociative');

        $service = new DeviceAuthService($db);

        $this->assertNull($service->authenticate(null));
        $this->assertNull($service->authenticate(''));
    }

    /**
     * Verifies not-found and disabled devices both return null.
     */
    public function testAuthenticateReturnsNullWhenDeviceNotFoundOrDisabled(): void
    {
        $db = $this->createMock(Connection::class);
        $db
            ->expects($this->exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                false,
                ['deviceId' => 'dev-1', 'enabled' => '0', 'areas' => serialize(['workshop'])],
            );

        $service = new DeviceAuthService($db);

        $this->assertNull($service->authenticate('token-a'));
        $this->assertNull($service->authenticate('token-b'));
    }

    /**
     * Verifies successful authentication normalizes, deduplicates, and filters areas.
     */
    public function testAuthenticateReturnsNormalizedDevicePayload(): void
    {
        $token = 'secret-token';
        $expectedHash = hash('sha256', $token);

        $db = $this->createMock(Connection::class);
        $db
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->stringContains('FROM tl_co_device'),
                $this->callback(static function (array $params) use ($expectedHash): bool {
                    return isset($params['hash']) && $params['hash'] === $expectedHash;
                }),
            )
            ->willReturn([
                'deviceId' => 'dev-99',
                'enabled' => '1',
                'areas' => serialize(['workshop', '', 'depot', 'workshop']),
            ]);

        $service = new DeviceAuthService($db);

        $result = $service->authenticate($token);

        $this->assertSame([
            'deviceId' => 'dev-99',
            'areas' => ['workshop', 'depot'],
        ], $result);
    }

    /**
     * Verifies null areas payload is normalized to an empty list.
     */
    public function testAuthenticateReturnsEmptyAreasWhenAreasColumnIsNull(): void
    {
        $db = $this->createMock(Connection::class);
        $db
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'deviceId' => 'dev-null-areas',
                'enabled' => '1',
                'areas' => null,
            ])
        ;

        $service = new DeviceAuthService($db);

        $result = $service->authenticate('token-with-null-areas');

        $this->assertSame([
            'deviceId' => 'dev-null-areas',
            'areas' => [],
        ], $result);
    }

    /**
     * Verifies enabled flag is strictly matched against string "1".
     */
    public function testAuthenticateReturnsNullWhenEnabledFlagIsNotStringOne(): void
    {
        $db = $this->createMock(Connection::class);
        $db
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'deviceId' => 'dev-typed-enabled',
                'enabled' => 1,
                'areas' => serialize(['workshop']),
            ])
        ;

        $service = new DeviceAuthService($db);

        $this->assertNull($service->authenticate('token-typed-enabled'));
    }
}
