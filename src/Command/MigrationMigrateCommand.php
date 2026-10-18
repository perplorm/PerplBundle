<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

declare(strict_types = 1);

namespace Propel\Bundle\PropelBundle\Command;

use Propel\Generator\Command\MigrationMigrateCommand as BaseMigrationCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
#[AsCommand(name: 'propel:migration:migrate', description: 'Execute all pending migrations')]
class MigrationMigrateCommand extends WrappedCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Connection to use. Example: default, bookstore')
            ->addOption('migration-table', null, InputOption::VALUE_OPTIONAL, 'Migration table name (if none given, the configured table is used)', null)
            ->addOption('output-dir', null, InputOption::VALUE_OPTIONAL, 'The output directory')
            ->addOption('fake', null, InputOption::VALUE_NONE, 'Does not touch the actual schema, but marks all migration as executed.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Continues with the migration even when errors occur.');
    }

    /**
     * {@inheritdoc}
     */
    protected function createSubCommandInstance(): Command
    {
        return new BaseMigrationCommand();
    }

    /**
     * {@inheritdoc}
     */
    protected function getSubCommandArguments(InputInterface $input): array
    {
        $config = $this->getConfig();
        $defaultOutputDir = $config['paths']['migrationDir'];

        return [
            '--connection' => $this->getConnections($input->getOption('connection')),
            '--migration-table' => $input->getOption('migration-table') ?: $this->getMigrationsTable(),
            '--output-dir' => $input->getOption('output-dir') ?: $defaultOutputDir,
            '--fake' => $input->getOption('fake'),
            '--force' => $input->getOption('force'),
        ];
    }
}
