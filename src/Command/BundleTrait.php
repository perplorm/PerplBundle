<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use Propel\Bundle\PropelBundle\AppBundle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use function array_keys;
use function in_array;
use function sprintf;
use function substr;
use function trim;

/**
 * @author Moritz Schroeder <moritz.schroeder@molabs.de>
 */
trait BundleTrait
{
    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    abstract protected function getContainer(): ContainerInterface;

    /**
     * Returns the selected bundle.
     * If no bundle argument is set, the user will get ask for it.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \Symfony\Component\HttpKernel\Bundle\BundleInterface
     */
    protected function getBundle(InputInterface $input, OutputInterface $output): BundleInterface
    {
        /** @var \Symfony\Component\HttpKernel\KernelInterface $kernel */
        $kernel = $this
            ->getContainer()
            ->get('kernel');

        if ($input->hasArgument('bundle') && !empty($input->getArgument('bundle')) && substr($input->getArgument('bundle'), 0, 1) === '@') {
            return $kernel->getBundle(substr($input->getArgument('bundle'), 1));
        }

        $bundles = $kernel->getBundles();
        $bundles[AppBundle::NAME] = new AppBundle($this->getContainer());

        $bundleNames = array_keys($bundles);

        do {
            $question = '<info>Select the bundle</info>: ';
            $question = new Question($question);
            $question->setAutocompleterValues($bundleNames);

            /** @var \Symfony\Component\Console\Helper\QuestionHelper $questionHelper */
            $questionHelper = $this->getHelperSet()->get('question');
            $bundleName = $questionHelper->ask($input, $output, $question);

            // old bundle structure and new one
            if (in_array($bundleName, $bundleNames) || !trim($bundleName) || $bundleName == AppBundle::NAME) {
                break;
            }
            $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</bg>', $bundleName));
        } while (true);

        if (!$bundleName || $bundleName === AppBundle::NAME) {
            return $bundles[AppBundle::NAME];
        }

        return $kernel->getBundle($bundleName);
    }
}
