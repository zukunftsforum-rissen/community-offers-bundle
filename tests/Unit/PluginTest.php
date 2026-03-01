<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;
use ZukunftsforumRissen\CommunityOffersBundle\CommunityOffersBundle;
use ZukunftsforumRissen\CommunityOffersBundle\ContaoManager\Plugin;

class PluginTest extends TestCase
{
    /**
     * Verifies plugin registers CommunityOffersBundle after ContaoCoreBundle.
     */
    public function testGetBundlesRegistersCommunityOffersBundle(): void
    {
        $plugin = new Plugin();

        $bundles = $plugin->getBundles($this->createStub(ParserInterface::class));

        $this->assertCount(1, $bundles);
        $this->assertInstanceOf(BundleConfig::class, $bundles[0]);
        $this->assertSame(CommunityOffersBundle::class, $bundles[0]->getName());
        $this->assertContains(ContaoCoreBundle::class, $bundles[0]->getLoadAfter());
    }

    /**
     * Verifies non-security extension configs are returned unchanged.
     */
    public function testGetExtensionConfigReturnsInputForNonSecurityExtension(): void
    {
        $plugin = new Plugin();
        $configs = [['foo' => 'bar']];

        $result = $plugin->getExtensionConfig('framework', $configs, new ContainerBuilder());

        $this->assertSame($configs, $result);
    }

    /**
     * Verifies security config prepends device firewall and access control rule.
     */
    public function testGetExtensionConfigPrependsDeviceFirewallAndAccessRule(): void
    {
        $plugin = new Plugin();

        $configs = [[
            'firewalls' => [
                'main' => ['stateless' => false],
            ],
            'access_control' => [
                ['path' => '^/foo', 'roles' => 'ROLE_USER'],
            ],
        ]];

        $result = $plugin->getExtensionConfig('security', $configs, new ContainerBuilder());

        $this->assertArrayHasKey('firewalls', $result[0]);
        $this->assertSame('device_api', array_key_first($result[0]['firewalls']));
        $this->assertSame('^/api/device', $result[0]['firewalls']['device_api']['pattern']);

        $this->assertArrayHasKey('access_control', $result[0]);
        $this->assertSame('^/api/device', $result[0]['access_control'][0]['path']);
        $this->assertSame('ROLE_DEVICE', $result[0]['access_control'][0]['roles']);
    }

    /**
     * Verifies prepended rule keeps existing access_control entries in original order.
     */
    public function testGetExtensionConfigKeepsExistingAccessControlEntriesAfterPrependedRule(): void
    {
        $plugin = new Plugin();

        $configs = [[
            'firewalls' => [
                'main' => ['stateless' => false],
            ],
            'access_control' => [
                ['path' => '^/foo', 'roles' => 'ROLE_USER'],
                ['path' => '^/bar', 'roles' => 'ROLE_ADMIN'],
            ],
        ]];

        $result = $plugin->getExtensionConfig('security', $configs, new ContainerBuilder());

        $this->assertSame('^/api/device', $result[0]['access_control'][0]['path']);
        $this->assertSame('ROLE_DEVICE', $result[0]['access_control'][0]['roles']);
        $this->assertSame('^/foo', $result[0]['access_control'][1]['path']);
        $this->assertSame('ROLE_USER', $result[0]['access_control'][1]['roles']);
        $this->assertSame('^/bar', $result[0]['access_control'][2]['path']);
        $this->assertSame('ROLE_ADMIN', $result[0]['access_control'][2]['roles']);
    }

    /**
     * Verifies device_api firewall is not duplicated when already configured.
     */
    public function testGetExtensionConfigDoesNotDuplicateExistingDeviceFirewall(): void
    {
        $plugin = new Plugin();

        $configs = [[
            'firewalls' => [
                'device_api' => ['pattern' => '^/api/device'],
                'main' => ['stateless' => false],
            ],
        ]];

        $result = $plugin->getExtensionConfig('security', $configs, new ContainerBuilder());

        $this->assertCount(2, $result[0]['firewalls']);
        $this->assertArrayHasKey('device_api', $result[0]['firewalls']);
    }

    /**
     * Verifies extension config iteration skips entries without firewalls until a valid one is found.
     */
    public function testGetExtensionConfigSkipsConfigsWithoutFirewallsUntilItFindsOne(): void
    {
        $plugin = new Plugin();

        $configs = [
            ['access_control' => [['path' => '^/foo', 'roles' => 'ROLE_USER']]],
            [
                'firewalls' => [
                    'main' => ['stateless' => false],
                ],
            ],
        ];

        $result = $plugin->getExtensionConfig('security', $configs, new ContainerBuilder());

        $this->assertSame($configs[0], $result[0]);
        $this->assertArrayHasKey('firewalls', $result[1]);
        $this->assertSame('device_api', array_key_first($result[1]['firewalls']));
        $this->assertArrayHasKey('access_control', $result[1]);
        $this->assertSame('^/api/device', $result[1]['access_control'][0]['path']);
        $this->assertSame('ROLE_DEVICE', $result[1]['access_control'][0]['roles']);
    }

    /**
     * Verifies non-array access_control values are normalized before prepending device rule.
     */
    public function testGetExtensionConfigNormalizesNonArrayAccessControl(): void
    {
        $plugin = new Plugin();

        $configs = [[
            'firewalls' => [
                'main' => ['stateless' => false],
            ],
            'access_control' => 'invalid',
        ]];

        $result = $plugin->getExtensionConfig('security', $configs, new ContainerBuilder());

        $this->assertArrayHasKey('access_control', $result[0]);
        $this->assertIsArray($result[0]['access_control']);
        $this->assertSame('^/api/device', $result[0]['access_control'][0]['path']);
        $this->assertSame('ROLE_DEVICE', $result[0]['access_control'][0]['roles']);
    }

    /**
     * Verifies plugin loads routes from the configured routes.yaml file.
     */
    public function testGetRouteCollectionLoadsConfiguredRoutesYaml(): void
    {
        $plugin = new Plugin();
        $expectedCollection = new RouteCollection();

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())
            ->method('load')
            ->with($this->stringContains('/config/routes.yaml'))
            ->willReturn($expectedCollection)
        ;

        $resolver = $this->createMock(LoaderResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with($this->stringContains('/config/routes.yaml'))
            ->willReturn($loader)
        ;

        $kernel = $this->createStub(KernelInterface::class);

        $result = $plugin->getRouteCollection($resolver, $kernel);

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * Verifies route loading fails with an error when resolver returns no loader.
     */
    public function testGetRouteCollectionThrowsErrorWhenResolverReturnsFalse(): void
    {
        $plugin = new Plugin();

        $resolver = $this->createMock(LoaderResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with($this->stringContains('/config/routes.yaml'))
            ->willReturn(false)
        ;

        $kernel = $this->createStub(KernelInterface::class);

        $this->expectException(\Error::class);

        $plugin->getRouteCollection($resolver, $kernel);
    }
}
