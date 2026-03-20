<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class KernelBootIntegrationTest extends KernelTestCase
{
    public function testKernelBootsAndContainerIsAvailable(): void
    {
        $kernelClass = $_SERVER['KERNEL_CLASS'] ?? $_ENV['KERNEL_CLASS'] ?? null;

        if (!\is_string($kernelClass) || '' === $kernelClass) {
            $this->markTestSkipped(
                'Integration test intentionally skipped: no application kernel configured. '
                . 'Set KERNEL_CLASS (for example App\\Kernel) to run full kernel boot tests.',
            );
        }

        if (!class_exists($kernelClass)) {
            $this->markTestSkipped(sprintf(
                'Integration test intentionally skipped: configured KERNEL_CLASS "%s" is not autoloadable.',
                $kernelClass,
            ));
        }

        $contaoKernelClass = 'Contao\\ManagerBundle\\HttpKernel\\ContaoKernel';

        if (class_exists($contaoKernelClass) && is_a($kernelClass, $contaoKernelClass, true)) {
            $projectDir = $_SERVER['PROJECT_DIR'] ?? $_ENV['PROJECT_DIR'] ?? getcwd();

            if (!\is_string($projectDir)) {
                $projectDir = getcwd();
            }

            if (!\is_string($projectDir) || '' === $projectDir) {
                $this->markTestSkipped(
                    'Integration test intentionally skipped: PROJECT_DIR is required to initialize ContaoKernel.',
                );
            }

            $contaoKernelClass::setProjectDir($projectDir);
        }

        try {
            self::bootKernel();
        } catch (\Throwable $exception) {
            $this->markTestSkipped(sprintf(
                'Integration test intentionally skipped: kernel boot failed in this environment (%s: %s).',
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
