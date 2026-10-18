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

use Propel\Generator\Command\ModelBuildCommand as BaseModelBuildCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
#[AsCommand(name: 'propel:model:build', description: 'Build the model classes based on Propel XML schemas')]
class ModelBuildCommand extends WrappedCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Connection to use. Example: default, bookstore')
            ->addOption('loader-script-dir', null, InputOption::VALUE_OPTIONAL, 'Target folder of the database table map loader script. Defaults to paths.loaderScriptDir', null)
            ->addArgument('bundle', InputArgument::OPTIONAL, 'The bundle to generate model classes from');
    }

    /**
     * {@inheritdoc}
     */
    protected function createSubCommandInstance(): Command
    {
        return new BaseModelBuildCommand();
    }

    /**
     * {@inheritdoc}
     */
    protected function getSubCommandArguments(InputInterface $input): array
    {
        $outputDir = $this->getKernel()->getProjectDir() . '/';

        return [
            '--output-dir' => $outputDir,
        ];
    }
}
