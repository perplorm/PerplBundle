<?php

declare(strict_types = 1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Propel\Bundle\PropelBundle\ArgumentResolver\PropelValueResolver;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('propel.value_resolver', PropelValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 110]);
};
