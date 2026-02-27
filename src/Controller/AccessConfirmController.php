<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;

class AccessConfirmController
{
    public function __construct(
        private readonly AccessRequestService $service,
    ) {
    }

    #[Route('/access/confirm/{token}', name: 'community_offers_access_confirm', methods: ['GET'])]
    public function confirm(string $token): RedirectResponse
    {
        $ok = $this->service->confirmToken($token);

        if (!$ok) {
            return new RedirectResponse('/zugangsanfrage-ungueltig');
        }

        return new RedirectResponse('/zugangsanfrage-bestaetigt');
    }
}
