<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use Propel\Generator\Command\GraphvizGenerateCommand as BaseGraphvizGenerateCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
#[AsCommand(name: 'propel:graphviz:generate', description: 'Generate Graphviz files (.dot)')]
class GraphvizGenerateCommand extends WrappedCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'The output directory', BaseGraphvizGenerateCommand::DEFAULT_OUTPUT_DIRECTORY);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSubCommandInstance(): Command
    {
        return new BaseGraphvizGenerateCommand();
    }

    /**
     * {@inheritdoc}
     */
    protected function getSubCommandArguments(InputInterface $input): array
    {
        return [
            '--output-dir' => $input->getOption('output-dir'),
        ];
    }
}
