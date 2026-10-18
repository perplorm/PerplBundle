<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Propel\Bundle\PropelBundle\Controller\PanelController;
use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $services = $container->services();

    $services->load('Propel\Bundle\PropelBundle\Command\\', '../src/Command/*Command.php')
        ->bind('$container', service('service_container'))
        ->tag('console.command');

    // used by the web profiler panel
    if (isset($builder->getParameter('kernel.bundles')['WebProfilerBundle'])) {
        $services->set(PanelController::class)
            ->public()
            ->args([service('twig'), service('profiler')->nullOnInvalid(), '%propel.configuration%', '%propel.logging%'])
            ->tag('controller.service_arguments');
    }
};
