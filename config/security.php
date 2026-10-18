<?php

declare(strict_types = 1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Propel\Bundle\PropelBundle\Security\User\PropelUserProvider;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('propel.security.user.provider.class', PropelUserProvider::class);

    $container->services()
        ->set('propel.security.user.provider', '%propel.security.user.provider.class%')
            ->abstract()
            ->private();
};
