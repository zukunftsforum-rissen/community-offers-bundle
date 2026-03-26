<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Frontend;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

final class AppController extends AbstractController
{
    /**
     * @param array<int, array{slug: string, title: string}> $areas
     */
    public function __construct(
        #[Autowire('%community_offers.app.login_path%')]
        private readonly string $loginPath,
        #[Autowire('%community_offers.app.logout_path%')]
        private readonly string $logoutPath,
        // #[Autowire('%community_offers.app.logout_redirect_path%')]
        // private readonly string $logoutRedirectPath,
        #[Autowire('%community_offers.app.areas%')]
        private readonly array $areas,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('@CommunityOffers/app/index.html.twig', [
            'title' => 'Zugänge',
            'appConfig' => [
                'loginPath' => $this->loginPath,
                'logoutPath' => $this->logoutPath,
                // 'logoutRedirectPath' => $this->logoutRedirectPath,
                'areas' => $this->areas,
            ],
        ]);
    }
}
