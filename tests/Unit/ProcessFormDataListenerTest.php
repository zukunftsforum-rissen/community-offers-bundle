<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\EventListener\ProcessFormDataListener;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;

class ProcessFormDataListenerTest extends TestCase
{
    /**
     * Verifies listener ignores submissions from unrelated forms.
     */
    public function testInvokeSkipsUnrelatedForms(): void
    {
        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->never())->method('createRequestAndSendDoiMail');

        $listener = new ProcessFormDataListener($accessRequestService);

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

        $listener = new ProcessFormDataListener($accessRequestService);

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
     * Verifies scalar requestedAreas are normalized to a list before forwarding.
     */
    public function testInvokeNormalizesScalarRequestedAreas(): void
    {
        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->once())
            ->method('createRequestAndSendDoiMail')
            ->with('', '', '', '', '', '', '', ['depot'])
        ;

        $listener = new ProcessFormDataListener($accessRequestService);

        $listener->__invoke(['requestedAreas' => 'depot'], ['formID' => 'access_request'], [], []);
    }

    /**
     * Verifies empty requestedAreas input is forwarded as an empty list.
     */
    public function testInvokeForwardsEmptyRequestedAreasListWhenMissing(): void
    {
        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->once())
            ->method('createRequestAndSendDoiMail')
            ->with('Max', 'Muster', 'max@example.org', '', '', '', '', [])
        ;

        $listener = new ProcessFormDataListener($accessRequestService);

        $listener->__invoke(
            [
                'firstname' => 'Max',
                'lastname' => 'Muster',
                'email' => 'max@example.org',
            ],
            ['formID' => 'access_request'],
            [],
            [],
        );
    }

    /**
     * Verifies empty entries are filtered from requestedAreas.
     */
    public function testInvokeFiltersEmptyRequestedAreasEntries(): void
    {
        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->once())
            ->method('createRequestAndSendDoiMail')
            ->with('', '', '', '', '', '', '', ['depot', 'sharing'])
        ;

        $listener = new ProcessFormDataListener($accessRequestService);

        $listener->__invoke(
            ['requestedAreas' => ['depot', '', 'sharing', 0]],
            ['formID' => 'access_request'],
            [],
            [],
        );
    }
}
