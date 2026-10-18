<?php

namespace Propel\Bundle\PropelBundle\Tests\Functional;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A kernel whose project directory is a scratch directory the commands can write to.
 */
class CommandTestKernel extends TestKernel
{
    public function __construct(private readonly string $projectDir)
    {
        parent::__construct('commands', true);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->projectDir.'/var/cache';
    }

    public function getLogDir(): string
    {
        return $this->projectDir.'/var/log';
    }

    public static function getDatabaseFile(string $projectDir): string
    {
        return $projectDir.'/var/test.sqlite';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('propel', [
            'database' => [
                'connections' => [
                    'default' => ['dsn' => 'sqlite:'.self::getDatabaseFile($this->projectDir)],
                ],
            ],
        ]);
    }
}
