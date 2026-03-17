<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Yaml\Yaml;
use ZukunftsforumRissen\CommunityOffersBundle\DependencyInjection\Configuration;

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
        $processor = new Processor();
        $processedConfig = $processor->processConfiguration(new Configuration(), [$config]);

        // $builder->setParameter('community_offers.logging_enabled', $processedConfig['logging_enabled']);
        // $builder->setParameter('community_offers.debug_logging_enabled', $processedConfig['debug_logging_enabled']);
        // $builder->setParameter('community_offers.mode', $processedConfig['mode']);

        // $builder->setParameter('community_offers.app.login_path', $processedConfig['app']['login_path']);
        // $builder->setParameter('community_offers.app.logout_path', $processedConfig['app']['logout_path']);
        // $builder->setParameter('community_offers.app.logout_redirect_path', $processedConfig['app']['logout_redirect_path']);

        $container->import('../config/services.yaml');
    }
}
