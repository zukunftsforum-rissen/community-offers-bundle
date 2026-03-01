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
        $this->projectDir = sys_get_temp_dir().'/community_offers_'.uniqid('', true);
        mkdir($this->projectDir.'/var/logs', 0777, true);
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
        $params = $this->createParameters('true', 'true', $this->projectDir);
        $service = new LoggingService($params);
        $this->assertInstanceOf(LoggingService::class, $service);
    }

    /**
     * Verifies info-level entries are written to the configured log file.
     */
    public function testWritesInfoMessagesToLogFileWhenLoggingIsEnabled(): void
    {
        $params = $this->createParameters('true', 'true', $this->projectDir);
        $service = new LoggingService($params);

        $service->initiateLogging('community-offers', 'phpunit-info');
        $service->info('Test info message', ['memberId' => 42]);

        $content = file_get_contents($this->projectDir.'/var/logs/phpunit-info.log');

        $this->assertIsString($content);
        $this->assertStringContainsString('Test info message', $content);
        $this->assertStringContainsString('memberId', $content);
    }

    /**
     * Verifies debug messages are suppressed when debug logging is disabled.
     */
    public function testDoesNotWriteDebugMessagesWhenDebugLoggingIsDisabled(): void
    {
        $params = $this->createParameters('true', 'false', $this->projectDir);
        $service = new LoggingService($params);

        $service->initiateLogging('community-offers', 'phpunit-debug-off');
        $service->debug('Debug should not be logged');

        $content = file_get_contents($this->projectDir.'/var/logs/phpunit-debug-off.log');

        $this->assertIsString($content);
        $this->assertStringNotContainsString('Debug should not be logged', $content);
    }

    private function createParameters(string $enableLogging, string $enableDebugLogging, string $projectDir): ParameterBagInterface
    {
        $params = $this->createStub(ParameterBagInterface::class);
        $params
            ->method('get')
            ->willReturnCallback(static fn (string $name): string => match ($name) {
                'enable_logging' => $enableLogging,
                'enable_debug_logging' => $enableDebugLogging,
                'kernel.project_dir' => $projectDir,
                default => '',
            });

        return $params;
    }

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

            $path = $directory.'/'.$entry;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
