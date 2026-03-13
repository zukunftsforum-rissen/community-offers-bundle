<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Yaml\Yaml;

final class CommunityOffersBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = Yaml::parseFile(__DIR__.'/../config/framework_workflow.yaml');

        if (\is_array($config) && isset($config['framework']) && \is_array($config['framework'])) {
            $builder->prependExtensionConfig('framework', $config['framework']);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }
}
