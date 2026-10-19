<?php

namespace Propel\Bundle\PropelBundle\Tests\Functional;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A kernel with query logging enabled and a default connection whose database cannot be opened.
 */
class UnreachableDatabaseKernel extends TestKernel
{
    public const DSN = 'sqlite:/nonexistent/perpl-bundle/test.sqlite';

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('propel', [
            'database' => [
                'connections' => [
                    'default' => ['dsn' => self::DSN],
                ],
            ],
            'runtime' => [
                'logging' => true,
            ],
        ]);

        $container->services()
            ->set('logger', MemoryLogger::class)
                ->public();
    }
}
