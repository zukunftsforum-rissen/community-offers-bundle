<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Database\Result;
use Contao\FrontendUser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorAuditLogger;

class DoorAuditLoggerTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setDatabaseSingleton(null);
    }

    public function testWritesExpectedInsertWhenFrontendUserExists(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $user = $this->getMockBuilder(FrontendUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $user->id = 42;

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('getUser')->willReturn($user);

        $executeCalls = [];

        $db = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();

        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO tl_co_door_log'))
            ->willReturn(new class(static function (array $args) use (&$executeCalls): void {
                $executeCalls[] = $args;
            }) {
                private $onExecute;

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

        $logger = new DoorAuditLogger($framework, $security);
        $logger->audit('open', 'depot', 'ok', 'Opened', ['jobId' => 99]);

        $this->assertCount(1, $executeCalls);
        $args = $executeCalls[0];

        $this->assertIsInt($args[0]);            // tstamp
        $this->assertSame('', $args[1]);         // correlationId
        $this->assertSame(42, $args[2]);         // memberId
        $this->assertSame('depot', $args[4]);    // area
        $this->assertSame('open', $args[5]);     // action
        $this->assertSame('ok', $args[6]);       // result
        $this->assertSame('', $args[7]);         // ip (blanked)
        $this->assertSame('', $args[8]);         // userAgent (blanked)
        $this->assertSame('Opened', $args[9]);   // message
        $this->assertStringContainsString('"jobId":99', (string) $args[10]); // context
    }

    public function testUsesFallbackValuesWhenNoFrontendUserExists(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('getUser')->willReturn(null);

        $executeCalls = [];

        $db = $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();

        $db->method('prepare')->willReturn(new class(static function (array $args) use (&$executeCalls): void {
            $executeCalls[] = $args;
        }) {
            private $onExecute;

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

        $logger = new DoorAuditLogger($framework, $security);
        $logger->audit('open', 'sharing', 'failed');

        $this->assertCount(1, $executeCalls);
        $args = $executeCalls[0];

        $this->assertSame('', $args[1]);         // correlationId
        $this->assertSame(0, $args[2]);          // memberId
        $this->assertSame('failed', $args[6]);   // result
        $this->assertSame('', $args[7]);         // ip (blanked)
        $this->assertSame('', $args[8]);         // userAgent (blanked)
        $this->assertSame('', $args[9]);         // message
        $this->assertNull($args[10]);            // context
    }

    private function setDatabaseSingleton(Database|null $database): void
    {
        $ref = new \ReflectionClass(Database::class);
        $property = $ref->getProperty('objInstance');
        $property->setAccessible(true);
        $property->setValue(null, $database);
    }
}
