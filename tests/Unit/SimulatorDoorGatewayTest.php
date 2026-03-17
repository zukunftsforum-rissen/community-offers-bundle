<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Door\SimulatorDoorGateway;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

final class SimulatorDoorGatewayTest extends TestCase
{
    public function testSupportsSimulatorType(): void
    {
        $logging = $this->createMock(LoggingService::class);
        $gateway = new SimulatorDoorGateway($logging);

        $this->assertTrue($gateway->supports(SimulatorDoorGateway::TYPE));
        $this->assertFalse($gateway->supports('raspberry'));
    }

    public function testReturnsStructuredResultAndLogsOpenCall(): void
    {
        $logging = $this->createMock(LoggingService::class);

        $logging
            ->expects($this->once())
            ->method('initiateLogging')
            ->with('door', 'community-offers');

        $logging
            ->expects($this->once())
            ->method('info')
            ->with('door.gateway.simulator_open', [
                'area' => 'workshop',
                'memberId' => 123,
            ]);

        $gateway = new SimulatorDoorGateway($logging);
        $result = $gateway->open('workshop', 123);

        $this->assertTrue($result->isOk());
        $this->assertSame('simulator_opened', $result->getStatus());
        $this->assertSame('Simulator door open triggered.', $result->getMessage());
        $this->assertSame([
            'area' => 'workshop',
            'memberId' => 123,
        ], $result->getContext());
    }
}
