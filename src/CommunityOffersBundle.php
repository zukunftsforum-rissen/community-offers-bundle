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

        $builder->prependExtensionConfig('monolog', [
            'channels' => ['community_offers'],
            'handlers' => [
                'community_offers' => [
                    'type' => 'rotating_file',
                    'path' => '%kernel.logs_dir%/community-offers.log',
                    'level' => 'debug',
                    'max_files' => 30,
                    'channels' => ['community_offers'],
                    'formatter' => 'monolog.formatter.community_offers',
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $processor = new Processor();
        $processor->processConfiguration(new Configuration(), [$config]);

        $container->import('../config/services.yaml');
    }
}
