<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Psr\Log\LoggerInterface;

class LoggingService
{
    private const SENSITIVE_CONTEXT_KEYS = [
        'authorization',
        'cookie',
        'nonce',
        'password',
        'token',
        'x-api-key',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $loggingEnabled = false,
        private readonly bool $debugLoggingEnabled = false,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function start(string $message, array $context = []): void
    {
        $this->info($message.'.start', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        if (!$this->loggingEnabled || !$this->debugLoggingEnabled) {
            return;
        }

        $context = $this->normalizeContext($context);
        $this->logger->debug($this->buildLogMessage($message, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $context = $this->normalizeContext($context);
        $this->logger->info($this->buildLogMessage($message, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $context = $this->normalizeContext($context);
        $this->logger->warning($this->buildLogMessage($message, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $context = $this->normalizeContext($context);
        $this->logger->error($this->buildLogMessage($message, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void
    {
        $context = $this->normalizeContext($context);
        $this->logger->critical($this->buildLogMessage($message, $context));
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

        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildLogMessage(string $message, array $context = []): string
    {
        if ([] === $context) {
            return $message;
        }

        $lines = [$message];

        foreach ($context as $key => $value) {
            $encoded = json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            $lines[] = \sprintf('    %s: %s', $key, false === $encoded ? 'null' : $encoded);
        }

        return implode("\n", $lines);
    }

    private function isSensitiveKey(string $key): bool
    {
        return \in_array(strtolower($key), self::SENSITIVE_CONTEXT_KEYS, true);
    }
}
