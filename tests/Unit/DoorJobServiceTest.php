<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;

/**
 * Unit tests for door job lifecycle and confirmation rules.
 *
 * Covered scenarios include:
 * - createOpenJob rate limits and lock behavior
 * - idempotent reuse of active jobs versus creating new jobs
 * - detailed confirm outcomes for missing, forbidden, timed-out, and successful confirmations
 * - final status transitions to executed, failed, and expired
 */
class DoorJobServiceTest extends TestCase
{
    /**
     * Verifies createOpenJob enforces rate limits and returns 429 with retry information.
     */
    public function testCreateOpenJobReturns429WhenRateLimitIsReached(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects($this->once())->method('executeStatement');
        $db->expects($this->never())->method('fetchAssociative');

        $rateItem = $this->createMock(CacheItemInterface::class);
        $rateItem->method('isHit')->willReturn(true);
        $rateItem->method('get')->willReturn([
            'count' => 3,
            'resetAt' => time() + 20,
        ]);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->willReturn($rateItem);
        $cache->expects($this->never())->method('save');

        $service = new DoorJobService($db, $cache);

        $result = $service->createOpenJob(10, 'depot', '127.0.0.1', 'phpunit');

        $this->assertFalse($result['ok']);
        $this->assertSame(429, $result['httpStatus']);
        $this->assertSame('Zu viele Versuche – bitte kurz warten.', $result['message']);
        $this->assertGreaterThan(0, $result['retryAfterSeconds']);
    }

    /**
     * Verifies active pending jobs are reused instead of creating duplicates.
     */
    public function testCreateOpenJobReusesExistingActiveJob(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects($this->once())->method('executeStatement');
        $db->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->stringContains('SELECT id, expiresAt, status'))
            ->willReturn([
                'id' => 22,
                'expiresAt' => time() + 10,
                'status' => 'pending',
            ])
        ;
        $db->expects($this->never())->method('insert');

        $rateItem = $this->createMock(CacheItemInterface::class);
        $rateItem->method('isHit')->willReturn(false);
        $rateItem->expects($this->once())->method('set')->with($this->isType('array'))->willReturnSelf();
        $rateItem->expects($this->once())->method('expiresAfter')->with($this->greaterThan(0))->willReturnSelf();

        $memberLockItem = $this->createMock(CacheItemInterface::class);
        $memberLockItem->method('isHit')->willReturn(false);

        $areaLockItem = $this->createMock(CacheItemInterface::class);
        $areaLockItem->method('isHit')->willReturn(false);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->exactly(3))
            ->method('getItem')
            ->willReturnOnConsecutiveCalls($rateItem, $memberLockItem, $areaLockItem)
        ;
        $cache->expects($this->once())->method('save')->with($rateItem)->willReturn(true);

        $service = new DoorJobService($db, $cache);

        $result = $service->createOpenJob(10, 'depot', '127.0.0.1', 'phpunit');

        $this->assertTrue($result['ok']);
        $this->assertSame(202, $result['httpStatus']);
        $this->assertSame('Job bereits aktiv.', $result['message']);
        $this->assertSame(22, $result['jobId']);
        $this->assertSame('pending', $result['status']);
    }

    /**
     * Verifies createOpenJob inserts a new job and writes member/area lock cache entries.
     */
    public function testCreateOpenJobCreatesNewJobAndSavesLocks(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects($this->once())->method('executeStatement');
        $db->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false)
        ;
        $db->expects($this->once())
            ->method('insert')
            ->with(
                'tl_co_door_job',
                $this->callback(static function (array $row): bool {
                    return 10 === $row['requestedByMemberId']
                        && 'depot' === $row['area']
                        && 'pending' === $row['status']
                        && '127.0.0.1' === $row['requestIp'];
                }),
            )
        ;
        $db->expects($this->once())->method('lastInsertId')->willReturn('55');

        $rateItem = $this->createMock(CacheItemInterface::class);
        $rateItem->method('isHit')->willReturn(false);
        $rateItem->method('set')->willReturnSelf();
        $rateItem->method('expiresAfter')->willReturnSelf();

        $memberLockItem = $this->createMock(CacheItemInterface::class);
        $memberLockItem->method('isHit')->willReturn(false);
        $memberLockItem->expects($this->once())->method('set')->with($this->isType('array'))->willReturnSelf();
        $memberLockItem->expects($this->once())->method('expiresAfter')->with(5)->willReturnSelf();

        $areaLockItem = $this->createMock(CacheItemInterface::class);
        $areaLockItem->method('isHit')->willReturn(false);
        $areaLockItem->expects($this->once())->method('set')->with($this->isType('array'))->willReturnSelf();
        $areaLockItem->expects($this->once())->method('expiresAfter')->with(5)->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->exactly(3))
            ->method('getItem')
            ->willReturnOnConsecutiveCalls($rateItem, $memberLockItem, $areaLockItem)
        ;
        $cache->expects($this->exactly(3))->method('save')->willReturn(true);

        $service = new DoorJobService($db, $cache);

        $result = $service->createOpenJob(10, 'depot', '127.0.0.1', 'phpunit');

        $this->assertTrue($result['ok']);
        $this->assertSame(202, $result['httpStatus']);
        $this->assertSame('Job angenommen.', $result['message']);
        $this->assertSame(55, $result['jobId']);
        $this->assertSame('pending', $result['status']);
        $this->assertIsInt($result['expiresAt']);
    }

    /**
     * Verifies job confirmations return 404 when the requested job does not exist.
     */
    public function testConfirmJobDetailedReturns404WhenJobIsMissing(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn(false);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 100, 'nonce', true);

        $this->assertSame(['accepted' => false, 'httpStatus' => 404, 'error' => 'not_found'], $result);
    }

    /**
     * Verifies already-final jobs are accepted when device and nonce match.
     */
    public function testConfirmJobDetailedReturns200ForFinalStatusWithMatchingDeviceAndNonce(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 1,
            'status' => 'executed',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-123',
            'dispatchedAt' => time(),
        ]);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 1, 'nonce-123', true);

        $this->assertSame(['accepted' => true, 'httpStatus' => 200, 'status' => 'executed'], $result);
    }

    /**
     * Verifies dispatched jobs reject confirmations from a different device.
     */
    public function testConfirmJobDetailedReturns403ForWrongDeviceOnDispatchedJob(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 1,
            'status' => 'dispatched',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-123',
            'dispatchedAt' => time(),
        ]);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-2', 1, 'nonce-123', true);

        $this->assertSame(['accepted' => false, 'httpStatus' => 403, 'error' => 'forbidden', 'status' => 'dispatched'], $result);
    }

    /**
     * Verifies confirmations outside the allowed confirm window expire the job.
     */
    public function testConfirmJobDetailedReturns410WhenConfirmWindowExpired(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 1,
            'status' => 'dispatched',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-123',
            'dispatchedAt' => time() - 31,
        ]);
        $db->expects($this->once())->method('executeStatement')->with(
            $this->stringContains("SET status='expired'"),
            ['id' => 1],
        );

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 1, 'nonce-123', true);

        $this->assertSame(['accepted' => false, 'httpStatus' => 410, 'error' => 'confirm_timeout', 'status' => 'expired'], $result);
    }

    /**
     * Verifies successful confirmations update job state to executed and return accepted.
     */
    public function testConfirmJobDetailedAcceptsSuccessfulExecution(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 7,
            'status' => 'dispatched',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-777',
            'dispatchedAt' => time(),
        ]);
        $db->expects($this->once())->method('executeStatement')->with(
            $this->stringContains('SET status=:status'),
            $this->callback(static function (array $params): bool {
                return 'executed' === $params['status']
                    && 'OK' === $params['resultCode']
                    && 7 === $params['id']
                    && 'dev-1' === $params['deviceId']
                    && 'nonce-777' === $params['nonce'];
            }),
        );

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 7, 'nonce-777', true, ['source' => 'test']);

        $this->assertSame(['accepted' => true, 'httpStatus' => 200, 'status' => 'executed'], $result);
    }

    private function createService(Connection $db): DoorJobService
    {
        return new DoorJobService($db, $this->createStub(CacheItemPoolInterface::class));
    }
}
