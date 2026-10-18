<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\DataFixtures;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Map\DatabaseMap;
use Propel\Runtime\Propel;
use Symfony\Component\Finder\Finder;
use function array_pop;
use function array_reverse;
use function class_exists;
use function constant;
use function count;
use function str_replace;
use function strlen;
use function strpos;
use function substr;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
abstract class AbstractDataHandler
{
    protected string $rootDir;

    protected ?ConnectionInterface $con = null;

    protected ?DatabaseMap $dbMap = null;

    /**
     * @var array{database?: array{connections: array<string, array<string, mixed>>}}
     */
    protected array $datasources = [];

    /**
     * Default constructor
     *
     * @param string $rootDir The root directory.
     * @param array{database?: array{connections: array<string, array<string, mixed>>}} $datasources
     */
    public function __construct(string $rootDir, array $datasources)
    {
        $this->rootDir = $rootDir;
        $this->datasources = $datasources;
    }

    /**
     * @return string
     */
    protected function getRootDir(): string
    {
        return $this->rootDir;
    }

    /**
     * Load Map builders.
     *
     * @param string|null $connectionName A connection name.
     *
     * @return void
     */
    protected function loadMapBuilders(?string $connectionName = null): void
    {
        if ($this->dbMap !== null) {
            return;
        }

        $this->dbMap = Propel::getDatabaseMap($connectionName);
        if (count($this->dbMap->getTables()) === 0) {
            $finder = new Finder();
            $files = $finder
                ->files()->name('*TableMap.php')
                ->in($this->getModelSearchPaths($connectionName))
                ->notName('TableMap.php')
                ->exclude('PropelBundle')
                ->exclude('Tests');

            foreach ($files as $file) {
                $class = $this->guessFullClassName($file->getRelativePath(), $file->getBasename('.php'));

                if ($class !== null && $this->isInDatabase($class, $connectionName)) {
                    $this->dbMap->addTableFromMapClass($class);
                }
            }
        }
    }

    /**
     * Check if a table is in a database
     *
     * @param string $class
     * @param string $connectionName
     *
     * @return bool
     */
    protected function isInDatabase(string $class, string $connectionName): bool
    {
        return constant($class . '::DATABASE_NAME') === $connectionName;
    }

    /**
     * Try to find a valid class with its namespace based on the filename.
     * Based on the PSR-0 standard, the namespace should be the directory structure.
     *
     * @param string $path The relative path of the file.
     * @param string $shortClassName The short class name aka the filename without extension.
     *
     * @return string|null
     */
    private function guessFullClassName(string $path, string $shortClassName): ?string
    {
        $array = [];
        $path = str_replace('/', '\\', $path);

        $array[] = $path;
        while ($pos = strpos($path, '\\')) {
            $path = substr($path, $pos + 1, strlen($path));
            $array[] = $path;
        }

        $array = array_reverse($array);
        while ($ns = array_pop($array)) {
            $class = $ns . '\\' . $shortClassName;
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Gets the search path for models out of the configuration.
     *
     * @param string $connectionName A connection name.
     *
     * @return array<string>
     */
    protected function getModelSearchPaths(string $connectionName): array
    {
        $searchPath = [];

        if (!empty($this->datasources['database']['connections'][$connectionName]['model_paths'])) {
            $modelPaths = $this->datasources['database']['connections'][$connectionName]['model_paths'];
            foreach ($modelPaths as $modelPath) {
                $searchPath[] = $this->getRootDir() . '/' . $modelPath;
            }
        } else {
            $searchPath[] = $this->getRootDir() . '/';
        }

        return $searchPath;
    }
}
