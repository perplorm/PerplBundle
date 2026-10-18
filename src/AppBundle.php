<?php

namespace Propel\Bundle\PropelBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * this class is made to simulate bundle containing base structure so we can work with no bundle architecture used from s4+
 * Class AppBundle
 */
class AppBundle extends Bundle
{
    /**
     * @var string
     */
    public const NAME = 'AppBundle';

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);

        $this->path = $this->container->get('kernel')->getProjectDir();
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return 'App';
    }
}
