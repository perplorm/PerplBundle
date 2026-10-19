<?php

namespace Propel\Bundle\PropelBundle\Tests\Functional;

use Propel\Bundle\PropelBundle\ArgumentResolver\PropelValueResolver;
use Propel\Bundle\PropelBundle\Controller\PanelController;
use Propel\Bundle\PropelBundle\Messenger\Transport\PropelTransport;
use Propel\Bundle\PropelBundle\Security\User\PropelUserProvider;
use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testServices()
    {
        $container = static::getContainer();

        $this->assertInstanceOf(PropelValueResolver::class, $container->get('propel.value_resolver'));
        $this->assertInstanceOf(PropelUserProvider::class, $container->get('security.user.provider.concrete.users'));
        $this->assertInstanceOf(PropelTransport::class, $container->get('messenger.transport.propel'));
    }

    public function testConnections()
    {
        $container = static::getContainer();
        $serviceContainer = Propel::getServiceContainer();

        $this->assertSame($serviceContainer->getWriteConnection('default'), $container->get('propel.connection'));
        $this->assertSame($serviceContainer->getWriteConnection('default'), $container->get('propel.default_connection'));
        $this->assertSame($serviceContainer->getWriteConnection('other'), $container->get('propel.other_connection'));
        $this->assertNotSame($container->get('propel.default_connection'), $container->get('propel.other_connection'));

        $consumer = $container->get(ConnectionConsumer::class);
        $this->assertSame($container->get('propel.connection'), $consumer->connection);
        $this->assertSame($container->get('propel.other_connection'), $consumer->otherConnection);
    }

    public function testResetWithoutDatabaseMaps()
    {
        // no loader script registered a database map for this kernel
        static::getContainer()->get('services_resetter')->reset();

        $this->assertTrue(true);
    }

    public function testProfilerPanel()
    {
        $response = static::getContainer()->get(PanelController::class)->configurationAction();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('sqlite::memory:', $response->getContent());
    }

    public function testCommands()
    {
        $application = new Application(static::bootKernel());

        foreach (['propel:build', 'propel:database:create', 'propel:fixtures:load', 'propel:form:generate', 'propel:migration:migrate', 'propel:sql:insert'] as $name) {
            $this->assertTrue($application->has($name), sprintf('Command "%s" is registered.', $name));
        }
    }
}
