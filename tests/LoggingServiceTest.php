<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LoggingServiceTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturn('true');
        $service = new LoggingService($params);
        $this->assertInstanceOf(LoggingService::class, $service);
    }
}
