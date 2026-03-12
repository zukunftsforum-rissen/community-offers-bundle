<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;

final class DoorWorkflowCorrelationTest extends KernelTestCase
{
    private Connection $db;
    private DoorJobService $service;

    protected function setUp(): void
    {

        $kernelClass = $_SERVER['KERNEL_CLASS'] ?? $_ENV['KERNEL_CLASS'] ?? null;

        if (!\is_string($kernelClass) || '' === $kernelClass || !class_exists($kernelClass)) {
            self::markTestSkipped('KERNEL_CLASS is not available or does not exist.');
        }

        if (method_exists($kernelClass, 'setProjectDir')) {
            $kernelClass::setProjectDir(\dirname(__DIR__, 4));
        }

        self::bootKernel();

        $container = static::getContainer();

        /** @var ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        $this->db = $doctrine->getConnection();

        /** @var DoorJobService $service */
        $service = $container->get('test.' . DoorJobService::class);
        $this->service = $service;
        $this->cleanupTestRows();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestRows();
        parent::tearDown();
    }

    private function cleanupTestRows(): void
    {
        $this->db->executeStatement(
            "DELETE FROM tl_co_door_log WHERE message LIKE 'phpunit-correlation-%'"
        );

        $this->db->executeStatement(
            "DELETE FROM tl_co_door_job WHERE userAgent = 'phpunit-correlation-test'"
        );
    }

    public function testDoorWorkflowKeepsSameCorrelationIdAcrossJobDispatchAndAudit(): void
    {
        $create = $this->service->createOpenJob(
            memberId: 1,
            area: 'workshop',
            ip: '127.0.0.1',
            userAgent: 'phpunit-correlation-test',
        );

        self::assertTrue(
            $create['ok'],
            'createOpenJob failed: ' . json_encode($create, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        self::assertArrayHasKey('jobId', $create);

        $jobId = (int) $create['jobId'];

        $jobRow = $this->db->fetchAssociative(
            'SELECT correlationId FROM tl_co_door_job WHERE id = ?',
            [$jobId]
        );

        self::assertIsArray($jobRow);
        $cid = (string) ($jobRow['correlationId'] ?? '');

        self::assertNotSame('', $cid);
        self::assertMatchesRegularExpression('/^[a-f0-9-]{64}$/i', $cid);

        $claimed = $this->service->dispatchJobs(
            deviceId: 'phpunit-device',
            areas: ['workshop'],
            limit: 1,
        );

        self::assertCount(1, $claimed);
        self::assertSame($cid, $claimed[0]['correlationId']);

        $nonce = (string) $claimed[0]['nonce'];

        $confirm = $this->service->confirmJobDetailed(
            deviceId: 'phpunit-device',
            jobId: $jobId,
            nonce: $nonce,
            ok: true,
            meta: ['source' => 'phpunit-correlation-test'],
        );

        self::assertTrue($confirm['accepted']);
        self::assertSame(200, $confirm['httpStatus']);

        $auditRow = $this->db->fetchAssociative(
            "SELECT correlationId, area, result, message
             FROM tl_co_door_log
             WHERE area = ?
             ORDER BY id DESC
             LIMIT 1",
            ['workshop']
        );

        self::assertIsArray($auditRow);
        self::assertSame($cid, (string) ($auditRow['correlationId'] ?? ''));
    }
}
