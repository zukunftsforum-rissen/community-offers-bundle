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
        $children = $rootNode->children();
        $children
            ->booleanNode('logging_enabled')
            ->defaultFalse()
            ->end()
        ;

        $children
            ->booleanNode('debug_logging_enabled')
            ->defaultFalse()
            ->end()
        ;

        $children
            ->enumNode('mode')
            ->values(['live', 'emulator'])
            ->defaultValue('live')
            ->end()
        ;

        $appNode = $children
            ->arrayNode('app')
            ->addDefaultsIfNotSet()
        ;

        $appChildren = $appNode->children();
        $appChildren
            ->scalarNode('url')
            ->defaultValue('/app')
            ->end()
        ;

        $appChildren
            ->scalarNode('login_path')
            ->defaultValue('/login')
            ->end()
        ;

        $appChildren
            ->scalarNode('logout_path')
            ->defaultValue('/_contao/logout')
            ->end()
        ;

        $appChildren
            ->scalarNode('reset_password_url')
            ->defaultValue('/password-reset')
            ->end()
        ;

        $appChildren
            ->scalarNode('areas')
            ->defaultValue('[]')
            ->info('JSON array of areas')
            ->end()
        ;

        $appChildren->end();
        $appNode->end();

        $children
            ->scalarNode('area_groups')
            ->defaultValue('{}')
            ->info('JSON object mapping area to group id')
            ->end()
        ;

        $children
            ->scalarNode('doi_ttl')
            ->defaultValue(172800) // 48h
            ->end()
        ;

        $children
            ->scalarNode('password_ttl')
            ->defaultValue(86400) // 24h
            ->end()
        ;

        $children
            ->scalarNode('confirm_window')
            ->defaultValue(30) // 30s
            ->end()
        ;

        $children->end();

        return $treeBuilder;
    }
}
