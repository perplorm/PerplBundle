<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

declare(strict_types = 1);

namespace Propel\Bundle\PropelBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use function array_keys;
use function count;

/**
 * PropelExtension loads the PropelBundle configuration.
 *
 * @author William DURAND <william.durand1@gmail.com>
 */
class PropelExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (count($config['database']['connections']) === 1) {
            $defaultConnection = array_keys($config['database']['connections'])[0];
            if (!isset($config['runtime']['defaultConnection'])) {
                $config['runtime']['defaultConnection'] = $defaultConnection;
            }
            if (!isset($config['generator']['defaultConnection'])) {
                $config['generator']['defaultConnection'] = $defaultConnection;
            }
        }

        $container->setParameter('propel.logging', $config['runtime']['logging']);
        $container->setParameter('propel.configuration', $config);

        // Load services
        if (!$container->hasDefinition('propel')) {
            $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
            $loader->load('propel.php');
            $loader->load('value_resolver.php');
            $loader->load('security.php');
            $loader->load('services.php');
        }
    }

    /**
     * @param array<mixed> $config
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     *
     * @return \Propel\Bundle\PropelBundle\DependencyInjection\Configuration
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($container->getParameter('kernel.debug'), $container->getParameter('kernel.project_dir'));
    }

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * @return string The alias
     */
    public function getAlias(): string
    {
        return 'propel';
    }
}
