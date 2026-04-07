<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorAuditLogger;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorWorkflowLogger;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use ZukunftsforumRissen\CommunityOffersBundle\Workflow\DoorJobWorkflowSubscriber;

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

        $service = $this->createService($db, $cache);

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

        $service = $this->createService($db, $cache);

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

        $service = $this->createService($db, $cache);

        $result = $service->createOpenJob(10, 'depot', '127.0.0.1', 'phpunit');

        $this->assertTrue($result['ok']);
        $this->assertSame(202, $result['httpStatus']);
        $this->assertSame('Job angenommen.', $result['message']);
        $this->assertSame(55, $result['jobId']);
        $this->assertSame('pending', $result['status']);
        $this->assertGreaterThan(0, $result['expiresAt']);
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
        $db->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains("SET status='expired'"),
                [
                    'id' => 1,
                    'deviceId' => 'dev-1',
                    'nonce' => 'nonce-123',
                ]
            )
            ->willReturn(1);

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
        $db->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('SET status=:status'),
                $this->callback(static function (array $params): bool {
                    return 'executed' === $params['status']
                        && 'OK' === $params['resultCode']
                        && 7 === $params['id']
                        && 'dev-1' === $params['deviceId']
                        && 'nonce-777' === $params['nonce'];
                }),
            )
            ->willReturn(1);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 7, 'nonce-777', true, ['source' => 'test']);

        $this->assertSame(['accepted' => true, 'httpStatus' => 200, 'status' => 'executed'], $result);
    }

    /**
     * Verifies failed execution confirmations update job state to failed.
     */
    public function testConfirmJobDetailedAcceptsFailedExecution(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 8,
            'status' => 'dispatched',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-888',
            'dispatchedAt' => time(),
        ]);
        $db->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('SET status=:status'),
                $this->callback(static function (array $params): bool {
                    return 'failed' === $params['status']
                        && 'ERR' === $params['resultCode']
                        && 8 === $params['id']
                        && 'dev-1' === $params['deviceId']
                        && 'nonce-888' === $params['nonce'];
                }),
            )
            ->willReturn(1);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 8, 'nonce-888', false);

        $this->assertSame(['accepted' => true, 'httpStatus' => 200, 'status' => 'failed'], $result);
    }

    /**
     * Verifies confirmation with wrong nonce is rejected with 403.
     */
    public function testConfirmJobDetailedReturns403ForWrongNonce(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 5,
            'status' => 'dispatched',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'correct-nonce',
            'dispatchedAt' => time(),
        ]);
        $db->expects($this->never())->method('executeStatement');

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 5, 'wrong-nonce', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(403, $result['httpStatus']);
        $this->assertSame('forbidden', $result['error']);
        $this->assertSame('dispatched', $result['status']);
    }

    /**
     * Verifies confirmation with empty nonce is rejected with 403.
     */
    public function testConfirmJobDetailedReturns403ForEmptyNonce(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 6,
            'status' => 'dispatched',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => '',
            'dispatchedAt' => time(),
        ]);
        $db->expects($this->never())->method('executeStatement');

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 6, 'any-nonce', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(403, $result['httpStatus']);
        $this->assertSame('forbidden', $result['error']);
    }

    /**
     * Verifies confirmation of 'failed' status job is idempotent when device and nonce match.
     */
    public function testConfirmJobDetailedReturns200ForFailedStatusWithMatchingDeviceAndNonce(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 2,
            'status' => 'failed',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-abc',
            'dispatchedAt' => time(),
        ]);
        $db->expects($this->never())->method('executeStatement');

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 2, 'nonce-abc', true);

        $this->assertTrue($result['accepted']);
        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('failed', $result['status']);
    }

    /**
     * Verifies confirmation of final status job with wrong device is rejected.
     */
    public function testConfirmJobDetailedReturns403ForFinalStatusWithWrongDevice(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 3,
            'status' => 'executed',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-xyz',
            'dispatchedAt' => time(),
        ]);
        $db->expects($this->never())->method('executeStatement');

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-2', 3, 'nonce-xyz', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(403, $result['httpStatus']);
        $this->assertSame('forbidden', $result['error']);
        $this->assertSame('executed', $result['status']);
    }

    /**
     * Verifies confirmation of final status job with wrong nonce is rejected.
     */
    public function testConfirmJobDetailedReturns403ForFinalStatusWithWrongNonce(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 4,
            'status' => 'executed',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'correct-nonce',
            'dispatchedAt' => time(),
        ]);
        $db->expects($this->never())->method('executeStatement');

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 4, 'wrong-nonce', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(403, $result['httpStatus']);
        $this->assertSame('forbidden', $result['error']);
        $this->assertSame('executed', $result['status']);
    }

    /**
     * Verifies confirmation of expired job with matching device and nonce returns 410.
     */
    public function testConfirmJobDetailedReturns410ForExpiredStatusWithMatchingCredentials(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 9,
            'status' => 'expired',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-999',
            'dispatchedAt' => time() - 100,
        ]);
        $db->expects($this->never())->method('executeStatement');

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 9, 'nonce-999', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(410, $result['httpStatus']);
        $this->assertSame('confirm_timeout', $result['error']);
        $this->assertSame('expired', $result['status']);
    }

    /**
     * Verifies confirmation of expired job with wrong device is rejected with 403.
     */
    public function testConfirmJobDetailedReturns403ForExpiredStatusWithWrongDevice(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 10,
            'status' => 'expired',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-aaa',
            'dispatchedAt' => time() - 100,
        ]);
        $db->expects($this->never())->method('executeStatement');

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-2', 10, 'nonce-aaa', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(403, $result['httpStatus']);
        $this->assertSame('forbidden', $result['error']);
        $this->assertSame('expired', $result['status']);
    }

    /**
     * Verifies confirmation of pending job returns 409 conflict.
     */
    public function testConfirmJobDetailedReturns409ForPendingStatus(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 11,
            'status' => 'pending',
            'dispatchToDeviceId' => '',
            'nonce' => '',
            'dispatchedAt' => 0,
        ]);
        $db->expects($this->never())->method('executeStatement');

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 11, 'any-nonce', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(409, $result['httpStatus']);
        $this->assertSame('not_dispatchable', $result['error']);
        $this->assertSame('pending', $result['status']);
    }

    /**
     * Verifies timeout during confirmation handles concurrent state changes.
     */
    public function testConfirmJobDetailedHandlesConcurrentExpiration(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'id' => 12,
                'status' => 'dispatched',
                'dispatchToDeviceId' => 'dev-1',
                'nonce' => 'nonce-bbb',
                'dispatchedAt' => time() - 35,
            ],
            [
                'status' => 'expired',
            ]
        );
        $db->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains("SET status='expired'"),
                [
                    'id' => 12,
                    'deviceId' => 'dev-1',
                    'nonce' => 'nonce-bbb',
                ]
            )
            ->willReturn(0);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 12, 'nonce-bbb', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(410, $result['httpStatus']);
        $this->assertSame('confirm_timeout', $result['error']);
        $this->assertSame('expired', $result['status']);
    }

    /**
     * Verifies timeout with concurrent execution is handled gracefully.
     */
    public function testConfirmJobDetailedHandlesConcurrentExecutionDuringTimeout(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'id' => 13,
                'status' => 'dispatched',
                'dispatchToDeviceId' => 'dev-1',
                'nonce' => 'nonce-ccc',
                'dispatchedAt' => time() - 35,
            ],
            [
                'status' => 'executed',
            ]
        );
        $db->expects($this->once())
            ->method('executeStatement')
            ->willReturn(0);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 13, 'nonce-ccc', true);

        $this->assertTrue($result['accepted']);
        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('executed', $result['status']);
    }

    /**
     * Verifies successful execution with metadata includes metadata in result message.
     */
    public function testConfirmJobDetailedIncludesMetadataInResultMessage(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn([
            'id' => 14,
            'status' => 'dispatched',
            'dispatchToDeviceId' => 'dev-1',
            'nonce' => 'nonce-ddd',
            'dispatchedAt' => time(),
        ]);
        $db->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('SET status=:status'),
                $this->callback(static function (array $params): bool {
                    return str_contains($params['resultMessage'], 'Door open executed')
                        && str_contains($params['resultMessage'], 'source');
                }),
            )
            ->willReturn(1);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 14, 'nonce-ddd', true, ['source' => 'integration-test', 'version' => '1.0']);

        $this->assertTrue($result['accepted']);
        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('executed', $result['status']);
    }

    /**
     * Verifies concurrent update during confirmation is handled correctly.
     */
    public function testConfirmJobDetailedHandlesConcurrentUpdateConflict(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'id' => 15,
                'status' => 'dispatched',
                'dispatchToDeviceId' => 'dev-1',
                'nonce' => 'nonce-eee',
                'dispatchedAt' => time(),
            ],
            [
                'status' => 'executed',
                'correlationId' => '',
            ]
        );
        $db->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('SET status=:status'),
                $this->callback(static function (array $params): bool {
                    return 'executed' === $params['status'];
                }),
            )
            ->willReturn(0);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 15, 'nonce-eee', true);

        $this->assertTrue($result['accepted']);
        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('executed', $result['status']);
    }

    /**
     * Verifies concurrent update to expired state during confirmation.
     */
    public function testConfirmJobDetailedHandlesConcurrentUpdateToExpired(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'id' => 16,
                'status' => 'dispatched',
                'dispatchToDeviceId' => 'dev-1',
                'nonce' => 'nonce-fff',
                'dispatchedAt' => time(),
            ],
            [
                'status' => 'expired',
                'correlationId' => '',
            ]
        );
        $db->expects($this->once())
            ->method('executeStatement')
            ->willReturn(0);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 16, 'nonce-fff', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(410, $result['httpStatus']);
        $this->assertSame('confirm_timeout', $result['error']);
        $this->assertSame('expired', $result['status']);
    }

    /**
     * Verifies concurrent update to unexpected state during confirmation.
     */
    public function testConfirmJobDetailedHandlesConcurrentUpdateToUnexpectedState(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'id' => 17,
                'status' => 'dispatched',
                'dispatchToDeviceId' => 'dev-1',
                'nonce' => 'nonce-ggg',
                'dispatchedAt' => time(),
            ],
            [
                'status' => 'pending',
                'correlationId' => '',
            ]
        );
        $db->expects($this->once())
            ->method('executeStatement')
            ->willReturn(0);

        $service = $this->createService($db);

        $result = $service->confirmJobDetailed('dev-1', 17, 'nonce-ggg', true);

        $this->assertFalse($result['accepted']);
        $this->assertSame(409, $result['httpStatus']);
        $this->assertSame('not_dispatchable', $result['error']);
        $this->assertSame('pending', $result['status']);
    }

    private function createService(Connection $db, CacheItemPoolInterface|null $cache = null): DoorJobService
    {
        return new DoorJobService(
            $db,
            $cache ?? $this->createStub(CacheItemPoolInterface::class),
            $this->createDoorJobStateMachine(),
            $this->createStub(DoorAuditLogger::class),
            30,
            new \ZukunftsforumRissen\CommunityOffersBundle\Service\DoorWorkflowLogger(
                $this->createStub(\ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService::class)
            ),
        );
    }

    private function createDoorJobStateMachine(): StateMachine
    {
        $definition = new Definition(
            ['pending', 'dispatched', 'executed', 'failed', 'expired'],
            [
                new Transition('dispatch', 'pending', 'dispatched'),
                new Transition('execute', 'dispatched', 'executed'),
                new Transition('fail', 'dispatched', 'failed'),
                new Transition('expire_pending', 'pending', 'expired'),
                new Transition('expire_dispatched', 'dispatched', 'expired'),
            ]
        );

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new DoorJobWorkflowSubscriber(30));

        return new StateMachine(
            $definition,
            new MethodMarkingStore(true, 'status'),
            $dispatcher,
            'door_job'
        );
    }
}
