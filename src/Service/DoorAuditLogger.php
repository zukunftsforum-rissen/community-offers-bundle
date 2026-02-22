<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FrontendUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class DoorAuditLogger
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {}

    /**
     * @param array<string,mixed> $context
     */
    public function audit(
        string $action,
        string $area,
        string $result,
        string $message = '',
        array $context = [],
    ): void {
        $this->framework->initialize();

        $req = $this->requestStack->getCurrentRequest();
        $ip = $req?->getClientIp() ?? '';
        $ua = (string) ($req?->headers->get('User-Agent') ?? '');

        $user = $this->security->getUser();
        $memberId = $user instanceof FrontendUser ? (int) $user->id : 0;

        Database::getInstance()
            ->prepare("
                INSERT INTO tl_co_door_log
                (tstamp, memberId, area, action, result, ip, userAgent, message, context)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")
            ->execute(
                time(),
                $memberId,
                $area,
                $action,
                $result,
                mb_substr($ip, 0, 64),
                mb_substr($ua, 0, 255),
                mb_substr($message, 0, 255),
                $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            );
    }
}
