<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FrontendUser;
use Symfony\Bundle\SecurityBundle\Security;

class DoorAuditLogger
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function audit(string $action, string $area, string $result, string $message = '', array $context = [], string $correlationId = '', int|null $memberId = null): void
    {
        $this->framework->initialize();

        if (null === $memberId) {
            $user = $this->security->getUser();
            $memberId = $user instanceof FrontendUser ? (int) $user->id : 0;
        }

        Database::getInstance()
            ->prepare('
                INSERT INTO tl_co_door_log
                (tstamp, correlationId, memberId, deviceId, area, action, result, ip, userAgent, message, context)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ')
            ->execute(
                time(),
                mb_substr($correlationId, 0, 64),
                $memberId,
                (string) ($context['deviceId'] ?? ''),
                $area,
                $action,
                $result,
                '',
                '',
                mb_substr($message, 0, 255),
                $this->encodeContext($context),
            )
        ;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encodeContext(array $context): string|null
    {
        $allowed = [];

        foreach (['jobId', 'deviceId', 'expiresAt', 'retryAfterSeconds', 'status', 'ok'] as $key) {
            if (\array_key_exists($key, $context)) {
                $allowed[$key] = $context[$key];
            }
        }

        if (isset($context['meta']) && \is_array($context['meta'])) {
            $allowed['meta'] = [
                'keys' => array_map('strval', array_keys($context['meta'])),
                'count' => \count($context['meta']),
            ];
        }

        if ([] === $allowed) {
            return null;
        }

        $encoded = json_encode($allowed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return false === $encoded ? null : $encoded;
    }
}
