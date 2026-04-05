<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests\Integration;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorAuditLogger;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;
use ZukunftsforumRissen\CommunityOffersBundle\Workflow\DoorJobWorkflowSubscriber;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorWorkflowLogger;

final class DoorWorkflowCorrelationTest extends KernelTestCase
{
    private Connection|null $db = null;
    private DoorJobService $service;

    protected function setUp(): void
    {

        $kernelClass = $_SERVER['KERNEL_CLASS'] ?? $_ENV['KERNEL_CLASS'] ?? null;

        if (!\is_string($kernelClass) || '' === $kernelClass || !class_exists($kernelClass)) {
            self::markTestSkipped(
                'Integration test intentionally skipped: requires a bootable application kernel. '
                . 'Set KERNEL_CLASS to run correlation workflow integration checks.',
            );
        }

        if (method_exists($kernelClass, 'setProjectDir')) {
            $projectDir = $_SERVER['PROJECT_DIR'] ?? $_ENV['PROJECT_DIR'] ?? getcwd();

            if (!\is_string($projectDir) || '' === $projectDir) {
                self::markTestSkipped(
                    'Integration test intentionally skipped: PROJECT_DIR is required to initialize ContaoKernel.',
                );
            }

            $kernelClass::setProjectDir($projectDir);
        }

        self::bootKernel();

        try {
            $container = static::getContainer();
        } catch (\LogicException) {
            $kernel = self::$kernel;
            if (null === $kernel) {
                self::fail('Kernel is not available after boot.');
            }

            $container = $kernel->getContainer();
        }

        /** @var ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        $this->db = $doctrine->getConnection();

        try {
            $this->db->executeQuery('SELECT 1');
        } catch (\Throwable $exception) {
            self::markTestSkipped(sprintf(
                'Integration test intentionally skipped: requires a reachable test database for real workflow persistence (%s: %s).',
                $exception::class,
                $exception->getMessage(),
            ));
        }

        $service = $this->resolveDoorJobService($container);
        if (!$service instanceof DoorJobService) {
            self::markTestSkipped(
                'Integration test intentionally skipped: DoorJobService is not directly retrievable from the compiled test container and fallback wiring failed.',
            );
        }

        $this->service = $service;
        $this->cleanupTestRows();
    }

    private function resolveDoorJobService(ContainerInterface $container): DoorJobService|null
    {
        foreach (['test.' . DoorJobService::class, DoorJobService::class] as $serviceId) {
            try {
                if (!$container->has($serviceId)) {
                    continue;
                }

                $service = $container->get($serviceId);
                if ($service instanceof DoorJobService) {
                    return $service;
                }
            } catch (\Throwable) {
                // Fall through to next candidate and finally to manual wiring.
            }
        }

        if (null === $this->db) {
            return null;
        }

        $cache = $this->firstService($container, ['cache.app', CacheItemPoolInterface::class]);
        if (!$cache instanceof CacheItemPoolInterface) {
            $cache = new class () implements CacheItemPoolInterface {
                public function getItem(string $key): \Psr\Cache\CacheItemInterface
                {
                    return new class ($key) implements \Psr\Cache\CacheItemInterface {
                        public function __construct(private readonly string $key)
                        {
                        }
                        public function getKey(): string
                        {
                            return $this->key;
                        }
                        public function get(): mixed
                        {
                            return null;
                        }
                        public function isHit(): bool
                        {
                            return false;
                        }
                        public function set(mixed $value): static
                        {
                            return $this;
                        }
                        public function expiresAt(?\DateTimeInterface $expiration): static
                        {
                            return $this;
                        }
                        public function expiresAfter(int|\DateInterval|null $time): static
                        {
                            return $this;
                        }
                    };
                }

                /** @return iterable<string, \Psr\Cache\CacheItemInterface> */
                public function getItems(array $keys = []): iterable
                {
                    return [];
                }
                public function hasItem(string $key): bool
                {
                    return false;
                }
                public function clear(): bool
                {
                    return true;
                }
                public function deleteItem(string $key): bool
                {
                    return true;
                }
                public function deleteItems(array $keys): bool
                {
                    return true;
                }
                public function save(\Psr\Cache\CacheItemInterface $item): bool
                {
                    return true;
                }
                public function saveDeferred(\Psr\Cache\CacheItemInterface $item): bool
                {
                    return true;
                }
                public function commit(): bool
                {
                    return true;
                }
            };
        }

        $workflow = $this->firstService($container, ['state_machine.door_job']);
        if (!$workflow instanceof WorkflowInterface) {
            $definition = new Definition(
                ['pending', 'dispatched', 'executed', 'failed', 'expired'],
                [
                    new Transition('dispatch', 'pending', 'dispatched'),
                    new Transition('execute', 'dispatched', 'executed'),
                    new Transition('fail', 'dispatched', 'failed'),
                    new Transition('expire_pending', 'pending', 'expired'),
                    new Transition('expire_dispatched', 'dispatched', 'expired'),
                ],
            );
            $dispatcher = new EventDispatcher();
            $dispatcher->addSubscriber(new DoorJobWorkflowSubscriber(30));

            $workflow = new StateMachine(
                $definition,
                new MethodMarkingStore(true, 'status'),
                $dispatcher,
                'door_job',
            );
        }

        $logging = $this->firstService($container, ['test.' . LoggingService::class, LoggingService::class]);
        if (!$logging instanceof LoggingService) {
            $logger = $this->createStub(\Psr\Log\LoggerInterface::class);
            $logging = new LoggingService($logger, true, true);
        }

        $audit = $this->firstService($container, ['test.' . DoorAuditLogger::class, DoorAuditLogger::class]);
        if (!$audit instanceof DoorAuditLogger) {
            $framework = $this->firstService($container, [ContaoFramework::class, 'contao.framework']);
            $security = $this->firstService($container, [Security::class, 'security.helper']);

            if ($framework instanceof ContaoFramework && $security instanceof Security) {
                $audit = new DoorAuditLogger($framework, $security);
            } else {
                $audit = new class ($this->db) extends DoorAuditLogger {
                    public function __construct(private readonly Connection $db)
                    {
                    }

                    public function audit(string $action, string $area, string $result, string $message = '', array $context = [], string $correlationId = '', int|null $memberId = null): void
                    {
                        $this->db->executeStatement(
                            'INSERT INTO tl_co_door_log (tstamp, correlationId, memberId, deviceId, area, action, result, ip, userAgent, message, context) VALUES (:tstamp, :correlationId, :memberId, :deviceId, :area, :action, :result, :ip, :userAgent, :message, :context)',
                            [
                                'tstamp' => time(),
                                'correlationId' => mb_substr($correlationId, 0, 64),
                                'memberId' => $memberId ?? 0,
                                'deviceId' => (string) ($context['deviceId'] ?? ''),
                                'area' => $area,
                                'action' => $action,
                                'result' => $result,
                                'ip' => '',
                                'userAgent' => '',
                                'message' => mb_substr($message, 0, 255),
                                'context' => null,
                            ],
                        );
                    }
                };
            }
        }

        return new DoorJobService(
            $this->db,
            $cache,
            $workflow,
            $audit,
            30,
            new \ZukunftsforumRissen\CommunityOffersBundle\Service\DoorWorkflowLogger(
                $this->createStub(\ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService::class)
            ),
        );
    }

    /**
     * @param list<string> $ids
     */
    private function firstService(ContainerInterface $container, array $ids): mixed
    {
        foreach ($ids as $id) {
            try {
                if ($container->has($id)) {
                    return $container->get($id);
                }
            } catch (\Throwable) {
                // Try next candidate.
            }

            try {
                return $container->get($id);
            } catch (\Throwable) {
                // Try next candidate.
            }
        }

        return null;
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupTestRows();
        } catch (\Throwable) {
            // Ignore cleanup failures in environments without a usable DB driver.
        }

        parent::tearDown();
    }

    private function cleanupTestRows(): void
    {
        if (null === $this->db) {
            return;
        }

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
            rateLimitMaxAttempts: 1,
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
