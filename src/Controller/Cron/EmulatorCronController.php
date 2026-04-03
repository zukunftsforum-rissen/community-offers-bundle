<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Cron;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use ZukunftsforumRissen\CommunityOffersBundle\Service\EmulatorTickService;

final class EmulatorCronController
{
    public function __construct(
        private readonly EmulatorTickService $emulatorTickService,
        private readonly string $cronToken,
    ) {
    }

    #[Route(
        path: '/_cron/emulator-tick',
        name: 'community_offers.cron.emulator_tick',
        methods: ['GET']
    )]
    public function __invoke(Request $request): Response
    {
        $token = (string) $request->query->get('token', '');

        if ('' === $this->cronToken || !hash_equals($this->cronToken, $token)) {
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        $processed = $this->emulatorTickService->runTick();

        return new Response(
            sprintf('OK: processed %d job(s)', $processed),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
