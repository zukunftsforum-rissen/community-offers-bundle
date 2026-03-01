<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Controller\Api\LoanController;

class LoanControllerTest extends TestCase
{
    /**
     * Verifies the loan controller can be instantiated.
     */
    public function testCanInstantiateController(): void
    {
        $controller = new LoanController();

        $this->assertInstanceOf(LoanController::class, $controller);
    }
}
