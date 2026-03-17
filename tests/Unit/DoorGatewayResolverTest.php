<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Door\DoorGatewayResolver;

final class DoorGatewayResolverTest extends TestCase
{
    public function testResolvesMatchingGateway(): void
    {
        $first = $this->createMock(DoorGatewayInterface::class);
        $first->method('supports')->with('mock')->willReturn(false);

        $second = $this->createMock(DoorGatewayInterface::class);
        $second->method('supports')->with('mock')->willReturn(true);

        $resolver = new DoorGatewayResolver([$first, $second]);

        $resolved = $resolver->resolve('mock');

        $this->assertSame($second, $resolved);
    }

    public function testThrowsWhenNoGatewayMatches(): void
    {
        $gateway = $this->createMock(DoorGatewayInterface::class);
        $gateway->method('supports')->with('unknown')->willReturn(false);

        $resolver = new DoorGatewayResolver([$gateway]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No door gateway found for device type "unknown".');

        $resolver->resolve('unknown');
    }
}
