<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\DependencyInjection;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use function array_key_first;
use function array_keys;
use function count;
use function interface_exists;
use function sprintf;

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

        $connections = $config['database']['connections'];
        $configuredDefault = !empty($config['runtime']['defaultConnection']) ? $config['runtime']['defaultConnection'] : null;

        // the runtime default is the one the configuration names, else the first one
        $defaultConnection = $configuredDefault ?? array_key_first($connections);
        $config['runtime']['defaultConnection'] = $defaultConnection;

        // the commands fall back to the connection named "default", as they always did
        $commandConnection = $configuredDefault ?? (isset($connections['default']) ? 'default' : $defaultConnection);

        if (count($connections) === 1 && !isset($config['generator']['defaultConnection'])) {
            $config['generator']['defaultConnection'] = $defaultConnection;
        }

        $container->setParameter('propel.logging', $config['runtime']['logging']);
        $container->setParameter('propel.configuration', $config);
        $container->setParameter('propel.dbal.default_connection', $commandConnection);

        // Load services
        if (!$container->hasDefinition('propel')) {
            $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
            $loader->load('propel.php');
            $loader->load('value_resolver.php');
            $loader->load('security.php');
            $loader->load('services.php');

            if (interface_exists(TransportFactoryInterface::class)) {
                $loader->load('messenger.php');
            }
        }

        $container->getDefinition('propel.instance_pool_resetter')->replaceArgument(0, array_keys($config['database']['connections']));

        $this->loadConnections(array_keys($config['database']['connections']), $defaultConnection, $container);
    }

    /**
     * Registers the write connection of each configured connection as a service, `propel.<name>_connection`, with
     * `propel.connection` for the default connection. Autowiring resolves `ConnectionInterface $<name>Connection` to
     * the named connection and a plain `ConnectionInterface` argument to the default one.
     *
     * @param array<string> $names
     * @param string $defaultConnection
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     *
     * @return void
     */
    protected function loadConnections(array $names, string $defaultConnection, ContainerBuilder $container): void
    {
        foreach ($names as $name) {
            $id = sprintf('propel.%s_connection', $name);

            $container->setDefinition($id, (new Definition(ConnectionInterface::class, [$name]))
                ->setFactory([new Reference('propel.service_container'), 'getWriteConnection'])
                ->setPublic(true));
            $container->registerAliasForArgument($id, ConnectionInterface::class, sprintf('%s connection', $name));
        }

        $container->setAlias('propel.connection', sprintf('propel.%s_connection', $defaultConnection))->setPublic(true);
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
