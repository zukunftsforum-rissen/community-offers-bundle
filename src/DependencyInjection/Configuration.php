<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('community_offers');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->booleanNode('logging_enabled')
            ->defaultFalse()
            ->end()
            ->booleanNode('debug_logging_enabled')
            ->defaultFalse()
            ->end()
            ->enumNode('mode')
            ->values(['live', 'emulator'])
            ->defaultValue('live')
            ->end()
            ->arrayNode('app')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('login_path')
            ->defaultValue('/login')
            ->end()
            ->scalarNode('logout_path')
            ->defaultValue('/_contao/logout')
            ->end()
            ->scalarNode('logout_redirect_path')
            ->defaultValue('/app')
            ->end()
            ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
