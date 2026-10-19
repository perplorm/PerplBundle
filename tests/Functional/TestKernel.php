<?php

namespace Propel\Bundle\PropelBundle\Tests\Functional;

use Propel\Bundle\PropelBundle\PropelBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new WebProfilerBundle();
        yield new PropelBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/perpl-bundle-tests/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/perpl-bundle-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'php_errors' => ['log' => true],
            'profiler' => ['collect' => false],
            'messenger' => [
                'transports' => ['propel' => 'propel://default?queue_name=kernel'],
            ],
        ]);

        $container->extension('security', [
            'providers' => [
                'users' => ['propel' => ['class' => 'Propel\Bundle\PropelBundle\Tests\Fixtures\Model\User', 'property' => 'username']],
            ],
            'firewalls' => [
                'main' => ['provider' => 'users'],
            ],
        ]);

        $container->extension('propel', [
            'database' => [
                'connections' => [
                    'default' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                    'other' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                ],
            ],
        ]);

        $container->services()
            ->set(ConnectionConsumer::class)
                ->autowire()
                ->public();
    }
}
