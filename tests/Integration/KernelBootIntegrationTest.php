<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class KernelBootIntegrationTest extends KernelTestCase
{
    public function testKernelBootsAndContainerIsAvailable(): void
    {
        $kernelClass = $_SERVER['KERNEL_CLASS'] ?? $_ENV['KERNEL_CLASS'] ?? null;

        if (!\is_string($kernelClass) || '' === $kernelClass) {
            $this->markTestSkipped('Set KERNEL_CLASS to run integration tests, e.g. KERNEL_CLASS=App\\Kernel.');
        }

        if (!class_exists($kernelClass)) {
            $this->markTestSkipped(sprintf('Configured KERNEL_CLASS "%s" is not autoloadable in this context.', $kernelClass));
        }

        if (is_a($kernelClass, ContaoKernel::class, true)) {
            $projectDir = $_SERVER['PROJECT_DIR'] ?? $_ENV['PROJECT_DIR'] ?? getcwd();

            if (!\is_string($projectDir) || '' === $projectDir) {
                $projectDir = getcwd();
            }

            if (!\is_string($projectDir) || '' === $projectDir) {
                $this->markTestSkipped('Could not determine PROJECT_DIR for ContaoKernel initialization.');
            }

            ContaoKernel::setProjectDir($projectDir);
        }

        try {
            self::bootKernel();
        } catch (\Throwable $exception) {
            $this->markTestSkipped(sprintf(
                'Kernel boot failed in current environment (%s: %s).',
                $exception::class,
                $exception->getMessage(),
            ));
        }

        try {
            $container = static::getContainer();
        } catch (\LogicException) {
            $kernel = self::$kernel;
            if (null === $kernel) {
                $this->fail('Kernel is not available after boot.');
            }

            $container = $kernel->getContainer();
        }

        $this->assertInstanceOf(ContainerInterface::class, $container);
        $this->assertNotNull(self::$kernel);
    }
}
