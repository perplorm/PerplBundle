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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use function file_exists;
use function realpath;
use function sprintf;
use function time;

/**
 * FixturesDumpCommand.
 *
 * @author William DURAND <william.durand1@gmail.com>
 */
#[AsCommand(name: 'propel:fixtures:dump', description: 'Dump data from database into YAML fixtures file.')]
class FixturesDumpCommand extends AbstractCommand
{
    /**
     * Default fixtures directory.
     */
    protected string $defaultFixturesDir = 'propel/fixtures';

    /**
     * @see Command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setHelp(<<<EOT
The <info>propel:fixtures:dump</info> dumps data from database into YAML fixtures file.

  <info>php app/console propel:fixtures:dump</info>

The <info>--connection</info> parameter allows you to change the connection to use.
The <info>--dir</info> parameter allows you to change the output directory.
The default connection is the active connection (propel.dbal.default_connection).
EOT)

            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Set this parameter to define a connection to use')
            ->addOption('dir', null, InputOption::VALUE_OPTIONAL, 'Set this parameter to define a fixture directory');
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixtureDir = $input->getOption('dir') ?: $this->defaultFixturesDir;
        $path = realpath($this->getKernel()->getProjectDir() . '/') . '/' . $fixtureDir;

        if (!file_exists($path)) {
            $output->writeln("<info>The $path folder does not exists.</info>");
            if ($this->askConfirmation($input, $output, '<question>Do you want me to create it for you ?</question> [Yes]')) {
                $fs = new Filesystem();
                $fs->mkdir($path);
                $this->writeNewDirectory($output, $path);
            } else {
                throw new IOException(sprintf('Unable to find the %s folder', $path));
            }
        }

        $filename = $path . '/fixtures_' . time() . '.yml';
        $dumper = $this->getContainer()->get('propel.dumper.yaml');

        $dumper->dump($filename, $input->getOption('connection'));

        $this->writeNewFile($output, $filename);

        return self::SUCCESS;
    }
}
