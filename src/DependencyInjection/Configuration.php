<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('community_offers');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->booleanNode('logging_enabled')
            ->defaultFalse()
            ->end()
            ->booleanNode('debug_logging_enabled')
            ->defaultFalse()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
