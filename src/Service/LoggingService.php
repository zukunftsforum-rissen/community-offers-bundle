<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LoggingService
{
    private Logger|null $logger = null;

    private bool $loggingEnabled;

    private bool $debugLoggingEnabled;

    private string $logDir;

    public function __construct(ParameterBagInterface $params)
    {
        $enableLogging = $params->has('enable_logging') ? (string) $params->get('enable_logging') : 'false';
        $enableDebugLogging = $params->has('enable_debug_logging') ? (string) $params->get('enable_debug_logging') : 'false';

        $this->loggingEnabled = 'true' === $enableLogging;
        $this->debugLoggingEnabled = 'true' === $enableDebugLogging;

        $projectDir = rtrim((string) $params->get('kernel.project_dir'), '/');
        $this->logDir = $projectDir . '/var/logs/';
    }

    public function initiateLogging(string $moduleName, string $fileName = ''): void
    {
        if (null !== $this->logger) {
            return;
        }

        if (!is_dir($this->logDir) && !mkdir($concurrentDirectory = $this->logDir, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(\sprintf('Log directory "%s" could not be created.', $this->logDir));
        }

        $logFileName = '' !== $fileName ? $fileName : 'app';
        $logFile = $this->logDir . $logFileName . '.log';

        $streamHandler = new RotatingFileHandler(
            $logFile,
            30,
            Level::Debug,
        );

        $this->logger = new Logger($moduleName);

        $output = "[%datetime%] %channel%.%level_name%: %message%\n%context%\n%extra%";
        $formatter = new LineFormatter($output, null, true, true);
        $formatter->includeStacktraces(true);
        $formatter->ignoreEmptyContextAndExtra(true);
        $formatter->allowInlineLineBreaks(true);

        $streamHandler->setFormatter($formatter);
        $this->logger->pushHandler($streamHandler);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function start(string $message, array $context = []): void
    {
        $this->log('info', $message . '.start', $context);
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

        $this->logger?->debug(
            '    current_method',
            [
                'method' => $currentMethod,
            ],
        );
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
        $formattedContext = [];

        foreach ($context as $key => $value) {
            $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $formattedContext[] = \sprintf('    %s: %s', $key, false === $encoded ? 'null' : $encoded);
        }

        return implode("\n", $formattedContext);
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
        $logMessage = '    ' . $message;

        if ('' !== $formattedContext) {
            $logMessage .= "\n" . $formattedContext;
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
                break;
        }
    }

    private function ensureInitialized(): void
    {
        if (null === $this->logger) {
            $this->initiateLogging('door', 'community-offers');
        }
    }
}
