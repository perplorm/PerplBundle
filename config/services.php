<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Propel\Bundle\PropelBundle\Controller\PanelController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function sprintf;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();

    $services->load('Propel\Bundle\PropelBundle\Command\\', '../src/Command/*Command.php')
        ->bind('$container', service('service_container'))
        ->tag('console.command');

    // the service IDs the commands had before they were registered under their class names
    $commands = [
        'Build',
        'DatabaseCreate',
        'DatabaseDrop',
        'DatabaseReverse',
        'FixturesDump',
        'FixturesLoad',
        'FormGenerate',
        'GraphvizGenerate',
        'MigrationDiff',
        'MigrationDown',
        'MigrationMigrate',
        'MigrationStatus',
        'MigrationUp',
        'ModelBuild',
        'SqlBuild',
        'SqlInsert',
        'TableDrop',
    ];
    foreach ($commands as $name) {
        $class = 'Propel\Bundle\PropelBundle\Command\\' . $name . 'Command';
        $services->alias('propel_bundle_propel.command.' . Container::underscore($name) . '_command', $class)
            ->deprecate('perplorm/perpl-bundle', '1.0', sprintf('The "%%alias_id%%" service alias is deprecated, use "%s" instead.', $class));
    }

    // used by the web profiler panel
    if (isset($builder->getParameter('kernel.bundles')['WebProfilerBundle'])) {
        $services->set(PanelController::class)
            ->public()
            ->args([service('twig'), service('profiler')->nullOnInvalid(), '%propel.configuration%', '%propel.logging%'])
            ->tag('controller.service_arguments');
    }
};
