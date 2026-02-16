<?php

// src/Service/LoggingService.php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LoggingService
{
    private Logger $logger;

    private Logger $loggerStart;

    private bool $loggingEnabled;

    private bool $debugLoggingEnabled;

    public function __construct(ParameterBagInterface $params)
    {
        $this->loggingEnabled = 'true' === $params->get('enable_logging');
        $this->debugLoggingEnabled = 'true' === $params->get('enable_debug_logging');
    }

    /**
     * Initialize logging To be called first before using the logging methods.
     *
     * @param string $fileName optional (if not set, default filename is 'app')
     */
    public function initiateLogging(string $moduleName, string $fileName = ''): void
    {
        $logFileName = $fileName ?: 'app';
        $logFile = __DIR__.'/../../var/logs/'.$logFileName.'.log';
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
     * Log a message idented by "Start Start Start" To be called at the entrance point
     * of a module.
     *
     * @param string               $message
     * @param array<string, mixed> $context
     */
    public function start($message, $context = []): void
    {
        $this->loggerStart->debug($message, $context);
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     */
    public function debug($message, $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     */
    public function info($message, $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     */
    public function error($message, $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     */
    public function critical($message, $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function logCurrentMethod(): void
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $currentMethod = $backtrace[1]['function'] ?? 'unknown';

        $this->logger->debug("  Current method: $currentMethod");
    }

    /**
     * @param array<string, mixed> $context
     */
    private function formatContext($context): string
    {
        $formattedContext = '';

        foreach ($context as $key => $value) {
            $formattedContext .= \sprintf('    %s: %s', $key, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $formattedContext;
    }

    /**
     * @param string               $level
     * @param string               $message
     * @param array<string, mixed> $context
     */
    private function log($level, $message, $context = []): void
    {
        if (!$this->loggingEnabled && 'critical' !== $level) {
            return;
        }

        $formattedContext = $this->formatContext($context);
        $logMessage = '    '.$message;
        if (!empty($formattedContext)) {
            $logMessage .= "\n".$formattedContext;
        }

        switch ($level) {
            case 'debug':
                if ($this->debugLoggingEnabled) {
                    $this->logger->debug($logMessage);
                }
                break;
            case 'info':
                $this->logger->info($logMessage);
                break;
            case 'error':
                $this->logger->error($logMessage);
                break;
            case 'critical':
                $this->logger->critical($logMessage);
                break;
            default:
                $this->logger->notice($logMessage);
        }
    }
}
