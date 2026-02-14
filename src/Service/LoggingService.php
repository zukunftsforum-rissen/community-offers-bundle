<?php
// src/Service/LoggingService.php

declare(strict_types=1);

namespace Zukunftsforu\CommunityOffersBundle\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Level;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Monolog\Formatter\LineFormatter;

class LoggingService
{
    private Logger $logger;
    private Logger $loggerStart;
    private bool $loggingEnabled;
    private bool $debugLoggingEnabled;
    private string $moduleName = '';

    public function __construct(
        private ParameterBagInterface $params
    ) {
        $this->loggingEnabled = $params->get('enable_logging') === "true";
        $this->debugLoggingEnabled = $params->get('enable_debug_logging') === "true";
    }

    /**
     * Initialize logging
     * To be called first before using the logging methods
     * 
     * @param string $moduleName 
     * @param string $fileName optional (if not set, default filename is 'app') 
     * @return void 
     */
    public function initiateLogging(string $moduleName, string $fileName = ''): void
    {
        $this->moduleName = $moduleName;
        $logFileName = $fileName ? $fileName : 'app';
        $logFile = __DIR__ . '/../../var/logs/' . $logFileName . '.log';
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


    private function formatContext(array $context): string
    {
        $formattedContext = '';
        foreach ($context as $key => $value) {
            $formattedContext .= sprintf("    %s: %s", $key, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        return $formattedContext;
    }


    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->loggingEnabled && $level !== 'critical') {
            return;
        }

        $formattedContext = $this->formatContext($context);
        $logMessage = '    ' . $message;
        if (!empty($formattedContext)) {
            $logMessage .= "\n" . $formattedContext;
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


    /**
     * Log a message idented by "Start Start Start" 
     * To be called at the entrance point of a module
     *  
     * @param string $message 
     * @param array $context 
     * @return void 
     */
    public function start(string $message, array $context = []): void
    {
        $this->loggerStart->debug($message, $context);
    }


    /**
     * @param string $message 
     * @param array $context 
     * @return void 
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }


    /**
     * @param string $message 
     * @param array $context 
     * @return void 
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }


    /**
     * @param string $message 
     * @param array $context 
     * @return void 
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }


    /**
     * @param string $message 
     * @param array $context 
     * @return void 
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }


    /** @return void  */
    public function logCurrentMethod(): void
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $currentMethod = $backtrace[1]['function'] ?? 'unknown';

        $this->logger->debug("  Current method: $currentMethod");
    }
}
