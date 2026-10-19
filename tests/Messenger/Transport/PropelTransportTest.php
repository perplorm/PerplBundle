<?php

namespace Propel\Bundle\PropelBundle\Tests\Messenger\Transport;

use DateTimeZone;
use PDO;
use Propel\Bundle\PropelBundle\Messenger\Transport\PropelReceivedStamp;
use Propel\Bundle\PropelBundle\Messenger\Transport\PropelTransport;
use Propel\Bundle\PropelBundle\Tests\Functional\TestKernel;
use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

/**
 * Runs the transport against the SQLite database of the test kernel.
 */
class PropelTransportTest extends KernelTestCase
{
    private const QUEUE_NAME = 'propel_transport_test';

    private MockClock $clock;
    private PropelTransport $transport;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        // a timezone with DST, to show that the stored timestamps do not depend on it
        $this->clock = new MockClock('now', 'Europe/Brussels');
        $this->transport = $this->createTransport('messenger_message', true);
    }

    public function testGetReturnsMessagesInOrderOfAvailabilityAndAckDeletesThem(): void
    {
        $this->transport->send(new Envelope(new TestMessage('later'), [new DelayStamp(2000)]));
        $this->transport->send(new Envelope(new TestMessage('now')));

        $this->assertSame(
            $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $this->fetchColumn('SELECT created_at FROM messenger_message ORDER BY available_at ASC LIMIT 1'),
            'Should store timestamps in UTC, like the Doctrine transport'
        );

        $first = $this->getSingleEnvelope();
        $this->assertSame('now', $this->getContent($first), 'Should return the undelayed message first');
        $this->assertSame([], $this->transport->get(), 'Should hold back the delayed message');
        $this->assertSame(0, $this->transport->getMessageCount(), 'Should count neither the delivered nor the delayed message');

        $this->clock->sleep(2);

        $this->assertSame(1, $this->transport->getMessageCount(), 'Should count the delayed message once it is available');

        $second = $this->getSingleEnvelope();
        $this->assertSame('later', $this->getContent($second), 'Should return the delayed message once it is available');

        $this->transport->ack($first);
        $this->transport->ack($second);

        $this->assertSame(0, $this->transport->getMessageCount());
        $this->assertSame([], $this->transport->get());
        $this->assertSame('0', $this->fetchColumn('SELECT COUNT(*) FROM messenger_message'), 'Should delete acknowledged messages');
    }

    public function testGetRedeliversAfterTheTimeout(): void
    {
        $sent = $this->transport->send(new Envelope(new TestMessage('content')));
        $id = $sent->last(TransportMessageIdStamp::class)?->getId();
        $this->assertIsInt($id);

        $this->getSingleEnvelope();
        $this->assertSame([], $this->transport->get(), 'Should not redeliver within the timeout');

        $this->clock->sleep(3601);

        $envelope = $this->getSingleEnvelope();
        $this->assertSame($id, $envelope->last(PropelReceivedStamp::class)?->id, 'Should redeliver the same message after the timeout');
        $this->assertSame($id, $envelope->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testRejectDeletesTheMessage(): void
    {
        $this->transport->send(new Envelope(new TestMessage('content')));

        $envelope = $this->getSingleEnvelope();
        $this->transport->reject($envelope);

        $this->assertNull($this->transport->find($envelope->last(PropelReceivedStamp::class)?->id));
        $this->assertSame([], iterator_to_array($this->transport->all()));
    }

    public function testFindAndAllOnlyReturnMessagesOfTheQueue(): void
    {
        $other = $this->createTransport('messenger_message', true, 'other_queue');
        $other->send(new Envelope(new TestMessage('other')));
        $sent = $this->transport->send(new Envelope(new TestMessage('mine')));
        $id = $sent->last(TransportMessageIdStamp::class)?->getId();

        $this->assertSame('mine', $this->getContent($this->transport->find($id) ?? $this->fail('Should find the message by id')));
        $this->assertNull($this->transport->find($other->send(new Envelope(new TestMessage('other')))->last(TransportMessageIdStamp::class)?->getId()));
        $this->assertNull($this->transport->find('not an id'));

        $all = iterator_to_array($this->transport->all(10));
        $this->assertCount(1, $all);
        $this->assertSame('mine', $this->getContent($all[0]));
        $this->assertSame(2, $other->getMessageCount());
    }

    public function testGetWrapsMessagesThatCannotBeDecoded(): void
    {
        $this->transport->send(new Envelope(new TestMessage('content')));
        $this->getConnection()->exec('UPDATE messenger_message SET body = \'not serialized\'');

        if (!method_exists(MessageDecodingFailedException::class, 'wrap')) {
            // Symfony 7.4
            try {
                $this->transport->get();
                $this->fail('Should rethrow the decoding failure');
            } catch (MessageDecodingFailedException) {
                $this->assertSame('0', $this->fetchColumn('SELECT COUNT(*) FROM messenger_message'), 'Should reject the message it cannot decode');
            }

            return;
        }

        $envelope = $this->getSingleEnvelope();

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage(), 'Should wrap the decoding failure for the worker to reject the message');
        $this->assertNotNull($envelope->last(PropelReceivedStamp::class), 'Should still allow rejecting the message');

        $this->transport->reject($envelope);
        $this->assertSame('0', $this->fetchColumn('SELECT COUNT(*) FROM messenger_message'));
    }

    public function testSetupCreatesTheTableOnce(): void
    {
        $transport = $this->createTransport('manual_message', false);

        try {
            $transport->send(new Envelope(new TestMessage('content')));
            $this->fail('Should not create the table when auto_setup is off');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('manual_message', $exception->getMessage());
        }

        $transport->setup();
        $transport->setup();

        $transport->send(new Envelope(new TestMessage('content')));
        $this->assertSame(1, $transport->getMessageCount());
        $this->assertSame('1', $this->fetchColumn('SELECT COUNT(*) FROM sqlite_master WHERE type = \'index\' AND tbl_name = \'manual_message\' AND name = \'manual_message_queue_idx\''), 'Should create the index');
    }

    public function testAutoSetupCreatesTheTableOnFirstUse(): void
    {
        $this->assertSame('0', $this->fetchColumn('SELECT COUNT(*) FROM sqlite_master WHERE type = \'table\' AND name = \'auto_message\''));

        $transport = $this->createTransport('auto_message', true);
        $this->assertSame(0, $transport->getMessageCount());

        $this->assertSame('1', $this->fetchColumn('SELECT COUNT(*) FROM sqlite_master WHERE type = \'table\' AND name = \'auto_message\''), 'Should create the table on first use');
    }

    public function testUnknownConnectionsFailWithATransportException(): void
    {
        $transport = new PropelTransport(new PhpSerializer(), $this->clock, 'unknown', self::QUEUE_NAME, 3600, 'messenger_message', false);

        $this->expectException(TransportException::class);
        $transport->getMessageCount();
    }

    private function createTransport(string $tableName, bool $autoSetup, string $queueName = self::QUEUE_NAME): PropelTransport
    {
        return new PropelTransport(new PhpSerializer(), $this->clock, 'default', $queueName, 3600, $tableName, $autoSetup);
    }

    private function getSingleEnvelope(): Envelope
    {
        $envelopes = [...$this->transport->get()];
        $this->assertCount(1, $envelopes);

        return $envelopes[0];
    }

    private function getContent(Envelope $envelope): string
    {
        $message = $envelope->getMessage();
        $this->assertInstanceOf(TestMessage::class, $message);

        return $message->content;
    }

    private function fetchColumn(string $sql): ?string
    {
        $statement = $this->getConnection()->query($sql);
        $this->assertNotFalse($statement);

        $value = $statement->fetchColumn();

        return $value === null || $value === false ? null : (string) $value;
    }

    private function getConnection(): \Propel\Runtime\Connection\ConnectionInterface
    {
        return Propel::getServiceContainer()->getWriteConnection('default');
    }
}
