<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function preg_replace;
use function sprintf;

/**
 * DatabaseCreateCommand class.
 * Useful to create a database.
 *
 * @author William DURAND
 */
#[AsCommand(name: 'propel:database:create', description: 'Create a given database or the default one.')]
class DatabaseCreateCommand extends AbstractCommand
{
    /**
     * @see Command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Set this parameter to define a connection to use');
    }

    /**
     * @see Command
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connectionName = $input->getOption('connection') ?: $this->getDefaultConnection();
        $config = $this->getConnectionData($connectionName);
        $dbName = $this->parseDbName($config['dsn']);

        if ($dbName === null) {
            $output->writeln('<error>No database name found.</error>');

            return self::FAILURE;
        } else {
            $query = 'CREATE DATABASE ' . $dbName . ';';
        }

        $manager = new ConnectionManagerSingle($connectionName);
        $manager->setConfiguration($this->getTemporaryConfiguration($config));

        /** @var \Propel\Runtime\ServiceContainer\StandardServiceContainer $serviceContainer */
        $serviceContainer = Propel::getServiceContainer();
        $serviceContainer->setAdapterClass($connectionName, $config['adapter']);
        $serviceContainer->setConnectionManager($manager);

        $connection = Propel::getConnection($connectionName);

        $statement = $connection->prepare($query);
        $statement->execute();

        $output->writeln(sprintf('<info>Database <comment>%s</comment> has been created.</info>', $dbName));

        // s 5.1 expect integer to be returned. Introduces Command::SUCCESS and Command::FAILURE constants
        return self::SUCCESS;
    }

    /**
     * Create a temporary configuration to connect to the database in order
     * to create a given database. This idea comes from Doctrine1.
     *
     * @see https://github.com/doctrine/doctrine1/blob/master/lib/Doctrine/Connection.php#L1491
     *
     * @param array<string, mixed> $config A Propel connection configuration.
     *
     * @return array<string, mixed>
     */
    private function getTemporaryConfiguration(array $config): array
    {
        $dbName = $this->parseDbName($config['dsn']);

        $config['dsn'] = preg_replace(
            '#;?(dbname|Database)=' . $dbName . '#',
            '',
            $config['dsn'],
        );

        return $config;
    }
}
