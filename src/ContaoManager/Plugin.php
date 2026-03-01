<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ExtensionPluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;
use ZukunftsforumRissen\CommunityOffersBundle\CommunityOffersBundle;

class Plugin implements BundlePluginInterface, RoutingPluginInterface, ExtensionPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(CommunityOffersBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): RouteCollection|null
    {
        return $resolver->resolve(__DIR__.'/../../config/routes.yaml')
            ->load(__DIR__.'/../../config/routes.yaml')
        ;
    }

    /**
     * Allows a plugin to override extension configuration.
     *
     * @param string                     $extensionName
     * @param list<array<string, mixed>> $extensionConfigs
     *
     * @return list<array<string, mixed>>
     */
    public function getExtensionConfig($extensionName, array $extensionConfigs, ContainerBuilder $container): array
    {
        if ('security' !== $extensionName) {
            return $extensionConfigs;
        }

        // Finde die Config, die bereits firewalls definiert (kommt i.d.R. aus
        // contao/manager-bundle)
        foreach ($extensionConfigs as $i => $config) {
            if (!isset($config['firewalls']) || !\is_array($config['firewalls'])) {
                continue;
            }

            // device_api davor einfügen (Reihenfolge ist wichtig!)
            $firewalls = $config['firewalls'];

            if (!isset($firewalls['device_api'])) {
                $firewalls = ['device_api' => [
                    'pattern' => '^/api/device',
                    'stateless' => true,
                    'provider' => 'contao.security.backend_user_provider',
                    'custom_authenticators' => [
                        'ZukunftsforumRissen\CommunityOffersBundle\Security\DeviceTokenAuthenticator',
                    ],
                ]] + $firewalls;
            }

            $extensionConfigs[$i]['firewalls'] = $firewalls;

            // access_control ist mergebar, aber wir setzen unsere Regel gern ganz nach vorn
            $ac = $config['access_control'] ?? [];
            if (!\is_array($ac)) {
                $ac = [];
            }

            array_unshift($ac, ['path' => '^/api/device', 'roles' => 'ROLE_DEVICE']);
            $extensionConfigs[$i]['access_control'] = $ac;

            return $extensionConfigs;
        }

        return $extensionConfigs;
    }
}
