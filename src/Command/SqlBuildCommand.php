<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use Propel\Generator\Command\SqlBuildCommand as CommandSqlBuildCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
#[AsCommand(name: 'propel:sql:build', description: 'Build SQL files')]
class SqlBuildCommand extends WrappedCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('sql-dir', null, InputOption::VALUE_REQUIRED, 'The SQL files directory')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, '')
            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Connection to use. Example: default, bookstore');
    }

    /**
     * {@inheritdoc}
     */
    protected function createSubCommandInstance(): Command
    {
        return new CommandSqlBuildCommand();
    }

    /**
     * {@inheritdoc}
     */
    protected function getSubCommandArguments(InputInterface $input): array
    {
        $config = $this->getConfig();
        $defaultSqlDir = $config['paths']['sqlDir'];

        return [
            '--connection' => $this->getConnections($input->getOption('connection')),
            '--output-dir' => $input->getOption('sql-dir') ?: $defaultSqlDir,
            '--overwrite' => $input->getOption('overwrite'),
        ];
    }
}
