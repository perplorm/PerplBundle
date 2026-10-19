<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Propel\Bundle\PropelBundle\Messenger\Transport\PropelTransportFactory;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('propel.messenger.transport_factory', PropelTransportFactory::class)
            ->args([service('clock')])
            ->tag('messenger.transport_factory');
};
