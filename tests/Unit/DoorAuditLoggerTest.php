<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Database\Result;
use Contao\FrontendUser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorAuditLogger;

class DoorAuditLoggerTest extends TestCase
{
    /**
     * Resets the static Contao database singleton between tests.
     */
    protected function tearDown(): void
    {
        $this->setDatabaseSingleton(null);
    }

    /**
     * Verifies audit writes an insert row with frontend user and request context metadata.
     */
    public function testWritesExpectedInsertWhenFrontendUserAndRequestContextExist(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $request = Request::create('/api/door/open/depot', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $request->headers->set('User-Agent', 'PHPUnit-Agent');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $user = $this->getMockBuilder(FrontendUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock()
        ;
        $user->id = 42;

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('getUser')->willReturn($user);

        $executeCalls = [];

        $db = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock()
        ;
        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO tl_co_door_log'))
            ->willReturn(new class(static function (array $args) use (&$executeCalls): void {
                $executeCalls[] = $args;
            }) {
                /** @var callable(array<int, mixed>): void */
                private $onExecute;

                /** @param callable(array<int, mixed>): void $onExecute */
                public function __construct(callable $onExecute)
                {
                    $this->onExecute = $onExecute;
                }

                public function execute(mixed ...$args): Result
                {
                    ($this->onExecute)($args);

                    return new Result([], 'insert');
                }
            })
        ;
        $this->setDatabaseSingleton($db);

        $logger = new DoorAuditLogger($framework, $requestStack, $security);
        $logger->audit('open', 'depot', 'ok', 'Opened', ['jobId' => 99]);

        $this->assertCount(1, $executeCalls);
        $args = $executeCalls[0];

        $this->assertIsInt($args[0]);
        $this->assertSame(42, $args[1]);
        $this->assertSame('depot', $args[2]);
        $this->assertSame('open', $args[3]);
        $this->assertSame('ok', $args[4]);
        $this->assertSame('127.0.0.1', $args[5]);
        $this->assertSame('PHPUnit-Agent', $args[6]);
        $this->assertSame('Opened', $args[7]);
        $this->assertIsString($args[8]);
        $this->assertStringContainsString('"jobId":99', (string) $args[8]);
    }

    /**
     * Verifies audit falls back to empty/default values without request and frontend user.
     */
    public function testUsesFallbackValuesWhenNoRequestAndNoFrontendUserExist(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $requestStack = new RequestStack();

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('getUser')->willReturn(null);

        $executeCalls = [];

        $db = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock()
        ;
        $db->method('prepare')->willReturn(new class(static function (array $args) use (&$executeCalls): void {
            $executeCalls[] = $args;
        }) {
            /** @var callable(array<int, mixed>): void */
            private $onExecute;

            /** @param callable(array<int, mixed>): void $onExecute */
            public function __construct(callable $onExecute)
            {
                $this->onExecute = $onExecute;
            }

            public function execute(mixed ...$args): Result
            {
                ($this->onExecute)($args);

                return new Result([], 'insert');
            }
        });
        $this->setDatabaseSingleton($db);

        $logger = new DoorAuditLogger($framework, $requestStack, $security);
        $logger->audit('open', 'sharing', 'failed');

        $this->assertCount(1, $executeCalls);
        $args = $executeCalls[0];

        $this->assertSame(0, $args[1]);
        $this->assertSame('', $args[5]);
        $this->assertSame('', $args[6]);
        $this->assertSame('', $args[7]);
        $this->assertNull($args[8]);
    }

    private function setDatabaseSingleton(Database|null $database): void
    {
        $ref = new \ReflectionClass(Database::class);
        $property = $ref->getProperty('objInstance');
        $property->setAccessible(true);
        $property->setValue(null, $database);
    }
}
