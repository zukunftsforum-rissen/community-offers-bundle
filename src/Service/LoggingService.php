<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LoggingService
{
    private Logger|null $logger = null;

    private Logger|null $loggerStart = null;

    private bool $loggingEnabled;

    private bool $debugLoggingEnabled;

    private string $logDir;

    public function __construct(ParameterBagInterface $params)
    {
        $this->loggingEnabled = 'true' === $params->get('enable_logging');
        $this->debugLoggingEnabled = 'true' === $params->get('enable_debug_logging');

        $projectDir = rtrim((string) $params->get('kernel.project_dir'), '/');
        $this->logDir = $projectDir.'/var/logs/';
    }

    public function initiateLogging(string $moduleName, string $fileName = ''): void
    {
        if (null !== $this->logger && null !== $this->loggerStart) {
            return;
        }

        $logFileName = $fileName ?: 'app';
        $logFile = $this->logDir.$logFileName.'.log';
        $streamHandler = new StreamHandler($logFile, Level::Debug);

        $this->logger = new Logger($moduleName);
        $output = "[%datetime%] %channel%.%level_name%: %message%\n%context%\n%extra%";
        $formatter = new LineFormatter($output, null, true, true);
        $formatter->includeStacktraces(true);
        $formatter->ignoreEmptyContextAndExtra(true);
        $formatter->allowInlineLineBreaks(true);
        $streamHandler->setFormatter($formatter);
        $this->logger->pushHandler($streamHandler);

        $this->loggerStart = new Logger($moduleName);
        $streamHandlerStart = new StreamHandler($logFile, Level::Debug);
        $outputStart = "START START START [%datetime%] %channel%.%level_name%: %message%\n%context%\n%extra%";
        $formatterStart = new LineFormatter($outputStart, null, true, true);
        $formatterStart->includeStacktraces(true);
        $formatterStart->ignoreEmptyContextAndExtra(true);
        $formatterStart->allowInlineLineBreaks(true);
        $streamHandlerStart->setFormatter($formatterStart);
        $this->loggerStart->pushHandler($streamHandlerStart);
        $this->loggerStart->debug('START START START');
    }

    /**
     * @param array<string, mixed> $context
     */
    public function start(string $message, array $context = []): void
    {
        $this->ensureInitialized();
        $this->loggerStart?->debug($message, $this->normalizeContext($context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function logCurrentMethod(): void
    {
        $this->ensureInitialized();
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $currentMethod = $backtrace[1]['function'] ?? 'unknown';

        $this->logger?->debug("  Current method: $currentMethod");
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        if (isset($context['correlationId']) && !isset($context['cid'])) {
            $context['cid'] = $context['correlationId'];
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function formatContext(array $context): string
    {
        $formattedContext = '';

        foreach ($context as $key => $value) {
            $formattedContext .= \sprintf('    %s: %s', $key, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $formattedContext;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->loggingEnabled && 'critical' !== $level) {
            return;
        }

        $this->ensureInitialized();
        $context = $this->normalizeContext($context);

        $formattedContext = $this->formatContext($context);
        $logMessage = '    '.$message;
        if ('' !== $formattedContext) {
            $logMessage .= "\n".$formattedContext;
        }

        switch ($level) {
            case 'debug':
                if ($this->debugLoggingEnabled) {
                    $this->logger?->debug($logMessage);
                }
                break;
            case 'info':
                $this->logger?->info($logMessage);
                break;
            case 'warning':
                $this->logger?->warning($logMessage);
                break;
            case 'error':
                $this->logger?->error($logMessage);
                break;
            case 'critical':
                $this->logger?->critical($logMessage);
                break;
            default:
                $this->logger?->notice($logMessage);
        }
    }

    private function ensureInitialized(): void
    {
        if (null === $this->logger || null === $this->loggerStart) {
            $this->initiateLogging('door', 'community-offers');
        }
    }
}
