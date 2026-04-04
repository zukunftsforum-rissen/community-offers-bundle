<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LoggingServiceTest extends TestCase
{
    private string $projectDir;

    /**
     * Creates an isolated temporary log directory for each test run.
     */
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/community_offers_' . uniqid('', true);
        mkdir($this->projectDir . '/var/logs', 0777, true);
    }

    /**
     * Removes the temporary log directory after each test.
     */
    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectDir);
    }

    /**
     * Verifies LoggingService can be constructed with expected parameters.
     */
    public function testCanInstantiateService(): void
    {
        $logger = $this->createStub(\Psr\Log\LoggerInterface::class);
        $service = new LoggingService($logger, true, true);
        $this->assertInstanceOf(LoggingService::class, $service);
    }

    /**
     * Verifies info-level entries are written to the configured log file.
     */
    public function testWritesInfoMessagesToLoggerWhenLoggingIsEnabled(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo("Test info message\n    memberId: 123")
            );
        $service = new LoggingService($logger, true, true);
        $service->info('Test info message', [
            'memberId' => 123,
        ]);
    }

    /**
     * Verifies debug messages are suppressed when debug logging is disabled.
     */
    public function testDoesNotWriteDebugMessagesWhenDebugLoggingIsDisabled(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->never())->method('debug');
        $service = new LoggingService($logger, true, false);
        $service->debug('Debug should not be logged');
    }

    // ...existing code...

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
