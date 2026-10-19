<?php

namespace Propel\Bundle\PropelBundle\Tests\Messenger\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Propel\Bundle\PropelBundle\Messenger\Transport\PropelTransport;
use Propel\Bundle\PropelBundle\Messenger\Transport\PropelTransportFactory;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class PropelTransportFactoryTest extends TestCase
{
    private PropelTransportFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new PropelTransportFactory(new MockClock());
    }

    public function testSupportsThePropelScheme(): void
    {
        $this->assertTrue($this->factory->supports('propel://default', []));
        $this->assertFalse($this->factory->supports('amqp://localhost', []));
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('provideValidDsns')]
    public function testCreateTransport(string $dsn, array $options): void
    {
        $transport = $this->factory->createTransport($dsn, $options, new PhpSerializer());

        $this->assertInstanceOf(PropelTransport::class, $transport);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function provideValidDsns(): iterable
    {
        yield 'defaults' => ['propel://default', []];
        yield 'query options' => ['propel://default?queue_name=failed&redeliver_timeout=60&table_name=queue_message&auto_setup=false', []];
        yield 'array options' => ['propel://default', ['queue_name' => 'failed', 'redeliver_timeout' => 60, 'table_name' => 'queue_message', 'auto_setup' => false]];
        yield 'transport name' => ['propel://default', ['transport_name' => 'async']];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('provideInvalidDsns')]
    public function testCreateTransportRejectsInvalidDsns(string $dsn, array $options, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->factory->createTransport($dsn, $options, new PhpSerializer());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function provideInvalidDsns(): iterable
    {
        yield 'no connection' => ['propel://', [], 'must name a connection'];
        yield 'unknown option' => ['propel://default?foo=bar', [], 'Unknown Propel transport option "foo"'];
        yield 'empty queue name' => ['propel://default?queue_name=', [], '"queue_name"'];
        yield 'zero timeout' => ['propel://default', ['redeliver_timeout' => 0], '"redeliver_timeout"'];
        yield 'invalid table name' => ['propel://default', ['table_name' => 'messenger message; DROP TABLE'], '"table_name"'];
        yield 'invalid auto setup' => ['propel://default?auto_setup=maybe', [], '"auto_setup"'];
    }
}
