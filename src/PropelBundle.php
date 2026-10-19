<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle;

use Exception;
use Propel\Bundle\PropelBundle\DependencyInjection\Security\UserProvider\PropelFactory;
use Propel\Runtime\Connection\ConnectionManagerPrimaryReplica;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use function array_merge;
use function dirname;
use function file_exists;
use function is_readable;
use function key;

/**
 * PropelBundle
 *
 * @author William DURAND <william.durand1@gmail.com>
 */
class PropelBundle extends Bundle
{
    /**
     * @return string
     */
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        try {
            $this->configureConnections();

            // the container only resets the services it has instantiated
            $this->container->get('propel.instance_pool_resetter');

            if ($this->container->getParameter('propel.logging')) {
                $this->configureLogging();
            }
        } catch (Exception $e) {
        }
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if ($container->hasExtension('security')) {
            /** @var \Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension $securityExtension */
            $securityExtension = $container->getExtension('security');
            $securityExtension->addUserProviderFactory(new PropelFactory('propel', 'propel.security.user.provider'));
        }
    }

    /**
     * @return void
     */
    protected function configureConnections(): void
    {
        $config = $this->container->getParameter('propel.configuration');
        $defaultConnection = !empty($config['runtime']['defaultConnection']) ? $config['runtime']['defaultConnection'] : key($config['database']['connections']);

        /** @var \Propel\Runtime\ServiceContainer\StandardServiceContainer $serviceContainer */
        $serviceContainer = Propel::getServiceContainer();
        $serviceContainer->setDefaultDatasource($defaultConnection);

        foreach ($config['database']['connections'] as $name => $connection) {
            if (!empty($connection['slaves'])) {
                $manager = new ConnectionManagerPrimaryReplica($name);

                // configure the master (write) connection
                $manager->setWriteConfiguration($connection);

                // configure the slave (read) connections
                $slaveConnections = [];
                foreach ($connection['slaves'] as $slave) {
                    $slaveConnections[] = array_merge($connection, [
                        'dsn' => $slave['dsn'],
                        'slaves' => null,
                    ]);
                }

                $manager->setReadConfiguration($slaveConnections);
            } else {
                $manager = new ConnectionManagerSingle($name);
                $manager->setConfiguration($connection);
            }

            $serviceContainer->setAdapterClass($name, $connection['adapter']);
            $serviceContainer->setConnectionManager($manager);

            // load database maps
            if (file_exists($config['paths']['loaderScriptDir'] . '/loadDatabase.php') && is_readable($config['paths']['loaderScriptDir'] . '/loadDatabase.php')) {
                require_once($config['paths']['loaderScriptDir'] . '/loadDatabase.php');
            }
        }
    }

    /**
     * @return void
     */
    protected function configureLogging(): void
    {
        /** @var \Propel\Runtime\ServiceContainer\StandardServiceContainer $serviceContainer */
        $serviceContainer = Propel::getServiceContainer();
        $serviceContainer->setLogger('defaultLogger', $this->container->get('propel.logger'));

        foreach ($serviceContainer->getConnectionManagers() as $manager) {
            /** @var \Propel\Runtime\Connection\ConnectionWrapper $connection */
            $connection = $manager->getReadConnection($serviceContainer->getAdapter($manager->getName()));
            $connection->setLogMethods(array_merge($connection->getLogMethods(), ['prepare']));

            /** @var \Propel\Runtime\Connection\ConnectionWrapper $connection */
            $connection = $manager->getWriteConnection($serviceContainer->getAdapter($manager->getName()));
            $connection->setLogMethods(array_merge($connection->getLogMethods(), ['prepare']));
        }
    }
}
