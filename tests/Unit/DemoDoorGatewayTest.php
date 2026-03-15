<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Door\DemoDoorGateway;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

class DemoDoorGatewayTest extends TestCase
{
    /**
     * Verifies Demo gateway logs open calls and reports success.
     */
    public function testReturnsTrueAndLogsOpenCallWhenOpenIsInvoked(): void
    {
        $logging = $this->createMock(LoggingService::class);

        $logging
            ->expects($this->once())
            ->method('initiateLogging')
            ->with('door', 'community-offers');

        $logging
            ->expects($this->once())
            ->method('info')
            ->with('door.gateway.demo_open', [
                'area' => 'workshop',
                'memberId' => 123,
            ]);

        $gateway = new DemoDoorGateway($logging);

        $this->assertTrue($gateway->open('workshop', 123));
    }
}
