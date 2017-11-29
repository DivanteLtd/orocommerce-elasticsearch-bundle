<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */

namespace Divante\Bundle\ElasticsearchBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class DivanteElasticsearchExtension
 */
class DivanteElasticsearchExtension extends Extension
{

    /**
     * Loads a specific configuration.
     *
     * @param array $configs An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('divante_elasticsearch.index.name', $config['index']);
        $container->setParameter('divante_elasticsearch.type.name', $config['type']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
