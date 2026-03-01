<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Controller\AccessConfirmController;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;

class AccessConfirmControllerTest extends TestCase
{
    /**
     * Verifies invalid confirmation tokens redirect to the invalid-request page.
     */
    public function testConfirmRedirectsToInvalidPageWhenTokenCannotBeConfirmed(): void
    {
        $service = $this->createMock(AccessRequestService::class);
        $service->expects($this->once())
            ->method('confirmToken')
            ->with('bad-token')
            ->willReturn(false)
        ;

        $controller = new AccessConfirmController($service);

        $response = $controller->confirm('bad-token');

        $this->assertSame('/zugangsanfrage-ungueltig', $response->getTargetUrl());
    }

    /**
     * Verifies valid confirmation tokens redirect to the success page.
     */
    public function testConfirmRedirectsToSuccessPageWhenTokenIsValid(): void
    {
        $service = $this->createMock(AccessRequestService::class);
        $service->expects($this->once())
            ->method('confirmToken')
            ->with('good-token')
            ->willReturn(true)
        ;

        $controller = new AccessConfirmController($service);

        $response = $controller->confirm('good-token');

        $this->assertSame('/zugangsanfrage-bestaetigt', $response->getTargetUrl());
    }
}
