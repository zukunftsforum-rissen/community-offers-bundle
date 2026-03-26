<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Controller\Frontend\AccessConfirmController;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\InternalNotificationMailer;
use Contao\MemberModel;
use ZukunftsforumRissen\CommunityOffersBundle\Service\MemberProvisioningResult;
use ZukunftsforumRissen\CommunityOffersBundle\Service\MemberProvisioningServiceInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Service\PasswordSetupServiceInterface;
use Symfony\Component\Mailer\MailerInterface;

class AccessConfirmControllerTest extends TestCase
{
    /**
     * Verifies invalid confirmation tokens redirect to the invalid-request page.
     */
    public function testConfirmRedirectsToInvalidPageWhenTokenCannotBeConfirmed(): void
    {
        $service = $this->createMock(AccessRequestService::class);
        $service->expects($this->once())
            ->method('confirmTokenAndGetRequestId')
            ->with('bad-token')
            ->willReturn(null)
        ;

        $memberProvisioningService = $this->createMock(MemberProvisioningServiceInterface::class);
        $passwordSetupService = $this->createMock(PasswordSetupServiceInterface::class);
        $internalMailerTransport = $this->createMock(MailerInterface::class);
        $internalMailerTransport->expects($this->never())->method('send');
        $internalNotificationMailer = new InternalNotificationMailer($internalMailerTransport, 'info@example.org', 'noreply@example.org');

        $controller = new AccessConfirmController($service, $memberProvisioningService, $passwordSetupService, $internalNotificationMailer);

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
            ->method('confirmTokenAndGetRequestId')
            ->with('good-token')
            ->willReturn(123)
        ;
        $service->expects($this->once())
            ->method('getRequestRow')
            ->with(123)
            ->willReturn([
                'firstname' => 'Max',
                'lastname' => 'Mustermann',
                'street' => 'Musterweg 1',
                'postal' => '22559',
                'city' => 'Hamburg',
                'mobile' => '040 12345',
                'email' => 'max@example.org',
                'requestedAreas' => serialize(['depot']),
            ])
        ;

        $memberProvisioningService = $this->createMock(MemberProvisioningServiceInterface::class);
        $memberProvisioningService->expects($this->once())
            ->method('createMemberFromConfirmedRequest')
            ->with(123)
            ->willReturn(new MemberProvisioningResult($this->createStub(MemberModel::class), true))
        ;
        $passwordSetupService = $this->createMock(PasswordSetupServiceInterface::class);
        $passwordSetupService->expects($this->once())
            ->method('createSetupTokenForRequest')
            ->with(123)
            ->willReturn('setup-token-abc')
        ;
        $internalMailerTransport = $this->createMock(MailerInterface::class);
        $internalMailerTransport->expects($this->once())->method('send');
        $internalNotificationMailer = new InternalNotificationMailer($internalMailerTransport, 'info@example.org', 'noreply@example.org');

        $controller = new AccessConfirmController($service, $memberProvisioningService, $passwordSetupService, $internalNotificationMailer);

        $response = $controller->confirm('good-token');

        $this->assertSame('/access/set-password/setup-token-abc', $response->getTargetUrl());
    }
}
