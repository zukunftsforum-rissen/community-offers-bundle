<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Demo;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class DemoController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('@CommunityOffers/demo/demo.html.twig', [
            'title' => 'Demo',
        ]);
    }
}
