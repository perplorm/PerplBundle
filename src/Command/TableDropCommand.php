<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use InvalidArgumentException;
use PDO;
use Propel\Runtime\Adapter\Pdo\MysqlAdapter;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_search;
use function array_walk;
use function count;
use function implode;
use function sprintf;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
#[AsCommand(name: 'propel:table:drop', description: 'Drop a given table or all tables in the database.')]
class TableDropCommand extends AbstractCommand
{
    use FormattingHelpers;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use. Example: default, bookstore')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Set this parameter to execute this action.')
            ->addArgument('table', InputArgument::IS_ARRAY, 'Set this parameter to défine which table to delete (default all the table in the database.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = Propel::getConnection($input->getOption('connection'));
        $adapter = Propel::getAdapter($connection->getName());

        if (!$adapter instanceof MysqlAdapter) {
            $output->writeln('<error>This command is MySQL only.</error>');

            return self::FAILURE;
        }

        if (!$input->getOption('force')) {
            $output->writeln('<error>You have to use the "--force" option to drop some tables.</error>');

            return self::FAILURE;
        }

        $tablesToDelete = $input->getArgument('table');
        $nbTable = count($tablesToDelete);
        $tablePlural = (($nbTable > 1 || $nbTable == 0) ? 's' : '' );

        if ($this->getKernel()->getEnvironment() === 'prod') {
            $count = $nbTable ?: 'all';

            $this->writeSection(
                $output,
                'WARNING: you are about to drop ' . $count . ' table' . $tablePlural . ' in production !',
                'bg=red;fg=white',
            );

            if ($this->askConfirmation($input, $output, 'Are you sure ? (y/n) ', false) === false) {
                $output->writeln('<info>Aborted, nice decision !</info>');

                return -2;
            }
        }

        $showStatement = $connection->prepare('SHOW TABLES;');
        $showStatement->execute();

        $allTables = $showStatement->fetchAll(PDO::FETCH_COLUMN);

        if ($nbTable) {
            foreach ($tablesToDelete as $tableToDelete) {
                if (!array_search($tableToDelete, $allTables)) {
                    throw new InvalidArgumentException(sprintf('Table %s doesn\'t exist in the database.', $tableToDelete));
                }
            }
        } else {
            $tablesToDelete = $allTables;
        }

        $connection->exec('SET FOREIGN_KEY_CHECKS = 0;');

        array_walk($tablesToDelete, function (&$table, $key, $dbAdapter) {
            $table = $dbAdapter->quoteIdentifierTable($table);
        }, $adapter);

        $tablesToDelete = implode(', ', $tablesToDelete);

        if ($tablesToDelete !== '') {
            $connection->exec('DROP TABLE ' . $tablesToDelete . ' ;');

            $output->writeln(sprintf('Table' . $tablePlural . ' <info><comment>%s</comment> has been dropped.</info>', $tablesToDelete));
        } else {
            $output->writeln('<info>No tables have been dropped</info>');
        }

        $connection->exec('SET FOREIGN_KEY_CHECKS = 1;');

        return self::SUCCESS;
    }
}
