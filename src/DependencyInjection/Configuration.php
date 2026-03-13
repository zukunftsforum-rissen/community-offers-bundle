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
        $children = $rootNode->children();

        $loggingNode = $children->booleanNode('logging_enabled');
        $loggingNode->defaultFalse();
        $loggingNode->end();

        $debugNode = $children->booleanNode('debug_logging_enabled');
        $debugNode->defaultFalse();
        $debugNode->end();

        return $treeBuilder;
    }
}
