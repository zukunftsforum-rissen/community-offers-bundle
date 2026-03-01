<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\FrontendUser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use ZukunftsforumRissen\CommunityOffersBundle\EventListener\ProcessFormDataListener;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;

class ProcessFormDataListenerTest extends TestCase
{
    /**
     * Verifies listener ignores submissions from unrelated forms.
     */
    public function testInvokeSkipsUnrelatedForms(): void
    {
        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->never())->method('createRequestAndSendDoiMail');

        $security = $this->createStub(Security::class);
        $accessService = $this->createStub(AccessService::class);

        $listener = new ProcessFormDataListener($accessRequestService, $security, $accessService);

        $listener->__invoke(['requestedAreas' => ['depot']], ['formID' => 'contact'], [], []);

        $this->addToAssertionCount(1);
    }

    /**
     * Verifies initial access request form forwards submitted data to request service.
     */
    public function testInvokeUsesSubmittedDataForInitialAccessRequest(): void
    {
        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->once())
            ->method('createRequestAndSendDoiMail')
            ->with(
                'Ada',
                'Lovelace',
                'ada@example.org',
                'Musterweg 1',
                '22559',
                'Hamburg',
                '+49 123',
                ['depot'],
            )
        ;

        $security = $this->createStub(Security::class);
        $accessService = $this->createStub(AccessService::class);

        $listener = new ProcessFormDataListener($accessRequestService, $security, $accessService);

        $listener->__invoke([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.org',
            'street' => 'Musterweg 1',
            'postal' => '22559',
            'city' => 'Hamburg',
            'mobile' => '+49 123',
            'requestedAreas' => 'depot',
        ], ['formID' => 'access_request'], [], []);
    }

    /**
     * Verifies additional access form is ignored when no frontend user is authenticated.
     */
    public function testInvokeSkipsAdditionalAccessRequestWhenUserIsNotFrontendUser(): void
    {
        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->never())->method('createRequestAndSendDoiMail');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $accessService = $this->createStub(AccessService::class);

        $listener = new ProcessFormDataListener($accessRequestService, $security, $accessService);

        $listener->__invoke(['requestedAreas' => ['depot']], ['formID' => 'additional_access_request'], [], []);
    }

    /**
     * Verifies already granted areas are filtered out before creating additional requests.
     */
    public function testInvokeFiltersAlreadyGrantedAreasForAdditionalRequest(): void
    {
        $user = $this->createFrontendUser(7, 'Max', 'Muster', 'max@example.org');

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->once())
            ->method('createRequestAndSendDoiMail')
            ->with('Max', 'Muster', 'max@example.org', '', '', '', '', ['sharing'])
        ;

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->expects($this->once())
            ->method('getGrantedAreasForMemberId')
            ->with(7)
            ->willReturn(['depot'])
        ;

        $listener = new ProcessFormDataListener($accessRequestService, $security, $accessService);

        $listener->__invoke(
            ['requestedAreas' => ['depot', 'sharing']],
            ['formID' => 'additional_access_request'],
            [],
            [],
        );
    }

    /**
     * Verifies no request is created when filtering leaves no additional areas.
     */
    public function testInvokeSkipsAdditionalRequestWhenNothingRemainsAfterFiltering(): void
    {
        $user = $this->createFrontendUser(7, 'Max', 'Muster', 'max@example.org');

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->never())->method('createRequestAndSendDoiMail');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->method('getGrantedAreasForMemberId')->with(7)->willReturn(['depot']);

        $listener = new ProcessFormDataListener($accessRequestService, $security, $accessService);

        $listener->__invoke(
            ['requestedAreas' => ['depot']],
            ['formID' => 'additional_access_request'],
            [],
            [],
        );
    }

    private function createFrontendUser(int $id, string $firstname, string $lastname, string $email): FrontendUser
    {
        $user = $this->getMockBuilder(FrontendUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock()
        ;

        $user->id = $id;
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;

        return $user;
    }
}
