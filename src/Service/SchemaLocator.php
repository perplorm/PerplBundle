<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

declare(strict_types = 1);

namespace Propel\Bundle\PropelBundle\Service;

use Propel\Bundle\PropelBundle\AppBundle;
use SplFileInfo;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use function array_merge;
use function file_exists;
use function is_dir;
use function iterator_count;
use function sprintf;
use function str_replace;
use const DIRECTORY_SEPARATOR;

class SchemaLocator
{
    protected ContainerInterface $container;

    protected FileLocatorInterface $fileLocator;

    /**
     * @var array{paths: array{schemaDir: string, sqlDir: string, migrationDir: string, composerDir: string, loaderScriptDir: string}}
     */
    protected array $configuration;

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param \Symfony\Component\Config\FileLocatorInterface $fileLocator
     * @param array{paths: array{schemaDir: string, sqlDir: string, migrationDir: string, composerDir: string, loaderScriptDir: string}} $configuration
     */
    public function __construct(ContainerInterface $container, FileLocatorInterface $fileLocator, array $configuration)
    {
        $this->container = $container;
        $this->fileLocator = $fileLocator;
        $this->configuration = $configuration;
    }

    /**
     * @param array<string, \Symfony\Component\HttpKernel\Bundle\BundleInterface> $bundles
     *
     * @return array<string, array{?\Symfony\Component\HttpKernel\Bundle\BundleInterface, \SplFileInfo}>
     */
    public function locateFromBundlesAndConfiguration(array $bundles): array
    {
        if (empty($bundles[AppBundle::NAME])) {
            $bundles[AppBundle::NAME] = new AppBundle($this->container);
        }

        $schemas = $this->locateFromBundles($bundles);

        $path = $this->configuration['paths']['schemaDir'] . '/schema.xml';
        if (file_exists($path)) {
            $schema = new SplFileInfo($path);
            $schemas[(string)$schema] = [null, $schema];
        }

        return $schemas;
    }

    /**
     * @param array<string, \Symfony\Component\HttpKernel\Bundle\BundleInterface> $bundles
     *
     * @return array<string, array{\Symfony\Component\HttpKernel\Bundle\BundleInterface, \SplFileInfo}>
     */
    public function locateFromBundles(array $bundles): array
    {
        $schemas = [];
        foreach ($bundles as $bundle) {
            $schemas = array_merge($schemas, $this->locateFromBundle($bundle));
        }

        return $schemas;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle
     *
     * @return array<string, array{\Symfony\Component\HttpKernel\Bundle\BundleInterface, \SplFileInfo}>
     */
    public function locateFromBundle(BundleInterface $bundle): array
    {
        // no bundle/bundle
        $dir = ($bundle->getName() === AppBundle::NAME) ? $bundle->getPath() . '/config' : $bundle->getPath() . '/Resources/config';

        $finalSchemas = [];

        if (is_dir($dir)) {
            $finder = new Finder();
            $schemas = $finder->files()->name('*schema.xml')->followLinks()->in($dir);

            if (iterator_count($schemas)) {
                foreach ($schemas as $schema) {
                    $logicalName = $this->transformToLogicalName($schema, $bundle);

                    $finalSchema = new SplFileInfo($this->fileLocator->locate($logicalName));

                    $finalSchemas[(string)$finalSchema] = [$bundle, $finalSchema];
                }
            }
        }

        return $finalSchemas;
    }

    /**
     * @param \SplFileInfo $schema
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle
     *
     * @return string
     */
    protected function transformToLogicalName(SplFileInfo $schema, BundleInterface $bundle)
    {
        // NOTE: for future research - i dont see why this function exists call of ->getRealPath() should do the job

        $schemaPath = str_replace(
            // no bundle/bundle
            $bundle->getPath() . DIRECTORY_SEPARATOR . ($bundle->getName() == AppBundle::NAME ? '' : 'Resources' . DIRECTORY_SEPARATOR) . 'config' . DIRECTORY_SEPARATOR,
            '',
            $schema->getRealPath(),
        );

        if ($bundle->getName() == AppBundle::NAME) {
            return sprintf('%s/config/%s', $bundle->getPath(), $schemaPath);
        }

        return sprintf('@%s/Resources/config/%s', $bundle->getName(), $schemaPath);
    }
}
