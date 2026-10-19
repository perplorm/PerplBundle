<?php

namespace Propel\Bundle\PropelBundle\Tests\Functional;

use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;
use Propel\Runtime\ServiceContainer\StandardServiceContainer;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The kernel boots when the query logging cannot open the connections, as when the propel:* commands create the
 * database.
 */
class UnreachableDatabaseTest extends KernelTestCase
{
    private ServiceContainerInterface $serviceContainer;

    protected static function getKernelClass(): string
    {
        return UnreachableDatabaseKernel::class;
    }

    protected function setUp(): void
    {
        // the bundle configures Propel's global service container when it boots
        $this->serviceContainer = Propel::getServiceContainer();
        Propel::setServiceContainer(new StandardServiceContainer());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Propel::setServiceContainer($this->serviceContainer);
    }

    public function testBootLogsThatQueryLoggingCouldNotBeEnabled(): void
    {
        $kernel = static::bootKernel(['environment' => 'unreachable_database', 'debug' => true]);

        /** @var \Propel\Bundle\PropelBundle\Tests\Functional\MemoryLogger $logger */
        $logger = $kernel->getContainer()->get('logger');

        $warnings = array_values(array_filter(
            $logger->records,
            static fn (array $record): bool => $record['level'] === LogLevel::WARNING && str_contains($record['message'], 'query logging'),
        ));

        $this->assertCount(1, $warnings);
        $this->assertStringStartsWith('Propel query logging could not be enabled: ', $warnings[0]['message']);
        $this->assertInstanceOf(\Exception::class, $warnings[0]['context']['exception']);

        // the connections were configured before the logging failed
        $this->assertTrue(Propel::getServiceContainer()->hasConnectionManager('default'));
    }
}
