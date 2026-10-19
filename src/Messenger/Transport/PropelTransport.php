<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Messenger\Transport;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use PDOStatement;
use Propel\Runtime\Adapter\Pdo\MysqlAdapter;
use Propel\Runtime\Adapter\Pdo\PgsqlAdapter;
use Propel\Runtime\Adapter\Pdo\SqliteAdapter;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\StatementInterface;
use Propel\Runtime\Exception\ExceptionInterface as PropelExceptionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;
use function array_map;
use function ceil;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function method_exists;
use function sprintf;
use function str_contains;
use const JSON_THROW_ON_ERROR;

/**
 * Stores messages in a table of a Propel connection.
 *
 * Timestamps are stored in UTC.
 */
final class PropelTransport implements TransportInterface, SetupableTransportInterface, ListableReceiverInterface, MessageCountAwareInterface
{
    /**
     * @var string
     */
    private const SQL_DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var string
     */
    private const AVAILABLE_CONDITION = 'queue_name = :queue AND (delivered_at IS NULL OR delivered_at < :redeliver) AND available_at <= :now';

    /**
     * @var bool
     */
    private bool $setUp = false;

    /**
     * @param \Symfony\Component\Messenger\Transport\Serialization\SerializerInterface $serializer
     * @param \Symfony\Component\Clock\ClockInterface $clock
     * @param string $connectionName the name of the Propel connection
     * @param string $queueName
     * @param int $redeliverTimeout seconds after which a delivered but unacknowledged message is delivered again
     * @param string $tableName
     * @param bool $autoSetup whether to create the table when it is missing
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ClockInterface $clock,
        private readonly string $connectionName,
        private readonly string $queueName,
        private readonly int $redeliverTimeout,
        private readonly string $tableName,
        private readonly bool $autoSetup,
    ) {
    }

    /**
     * @param \Symfony\Component\Messenger\Envelope $envelope
     *
     * @return \Symfony\Component\Messenger\Envelope
     */
    public function send(Envelope $envelope): Envelope
    {
        $encodedEnvelope = $this->serializer->encode($envelope);
        $delayStamp = $envelope->last(DelayStamp::class);
        $delaySeconds = $delayStamp instanceof DelayStamp ? (int)ceil($delayStamp->getDelay() / 1000) : 0;
        $now = $this->getNow();

        $id = $this->guard(function () use ($encodedEnvelope, $delaySeconds, $now): int {
            $connection = $this->getConnection();

            $this->execute(
                $connection,
                sprintf('INSERT INTO %s (body, headers, queue_name, created_at, available_at) VALUES (:body, :headers, :queue, :created, :available)', $this->tableName),
                [
                    'body' => $encodedEnvelope['body'],
                    'headers' => json_encode($encodedEnvelope['headers'] ?? [], JSON_THROW_ON_ERROR),
                    'queue' => $this->queueName,
                    'created' => $now->format(self::SQL_DATE_FORMAT),
                    'available' => $now->modify(sprintf('%+d seconds', $delaySeconds))->format(self::SQL_DATE_FORMAT),
                ],
            );

            return (int)$connection->lastInsertId();
        });

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    /**
     * Receives the messages available first and marks them delivered, in one transaction.
     *
     * @param int $fetchSize how many messages to receive at most
     *
     * @return list<\Symfony\Component\Messenger\Envelope>
     */
    public function get(int $fetchSize = 1): iterable
    {
        $fetchSize = max(1, $fetchSize);
        $now = $this->getNow();

        $rows = $this->guard(function () use ($fetchSize, $now): array {
            $connection = $this->getConnection();

            return $connection->transaction(function () use ($connection, $fetchSize, $now): array {
                $rows = $this->fetchAvailable($connection, $now, sprintf('ORDER BY available_at ASC LIMIT %d %s', $fetchSize, $this->getLockClause()))->fetchAll(PDO::FETCH_ASSOC);

                if (!$rows) {
                    return [];
                }

                $parameters = ['now' => $now->format(self::SQL_DATE_FORMAT)];
                $placeholders = [];

                foreach ($rows as $index => $row) {
                    $placeholders[] = ':id' . $index;
                    $parameters['id' . $index] = (int)$row['id'];
                }

                $this->execute(
                    $connection,
                    sprintf('UPDATE %s SET delivered_at = :now WHERE id IN (%s)', $this->tableName, implode(', ', $placeholders)),
                    $parameters,
                );

                return $rows;
            });
        });

        return array_map($this->decodeRow(...), $rows);
    }

    /**
     * @param \Symfony\Component\Messenger\Envelope $envelope
     *
     * @return void
     */
    public function ack(Envelope $envelope): void
    {
        $this->deleteMessage($envelope);
    }

    /**
     * @param \Symfony\Component\Messenger\Envelope $envelope
     *
     * @return void
     */
    public function reject(Envelope $envelope): void
    {
        $this->deleteMessage($envelope);
    }

    /**
     * @return int
     */
    public function getMessageCount(): int
    {
        $now = $this->getNow();

        return $this->guard(function () use ($now): int {
            $statement = $this->execute(
                $this->getConnection(),
                sprintf('SELECT COUNT(*) FROM %s WHERE %s', $this->tableName, self::AVAILABLE_CONDITION),
                $this->getAvailableParameters($now),
            );

            return (int)$this->fetchValue($statement);
        });
    }

    /**
     * @param int|null $limit
     *
     * @return iterable<\Symfony\Component\Messenger\Envelope>
     */
    public function all(?int $limit = null): iterable
    {
        $now = $this->getNow();
        $suffix = 'ORDER BY available_at ASC' . ($limit === null ? '' : sprintf(' LIMIT %d', $limit));

        $rows = $this->guard(fn (): array => $this->fetchAvailable($this->getConnection(), $now, $suffix)->fetchAll(PDO::FETCH_ASSOC));

        foreach ($rows as $row) {
            yield $this->decodeRow($row);
        }
    }

    /**
     * @param mixed $id
     *
     * @return \Symfony\Component\Messenger\Envelope|null
     */
    public function find(mixed $id): ?Envelope
    {
        if (!is_numeric($id)) {
            return null;
        }

        $row = $this->guard(fn (): mixed => $this->fetchRow($this->execute(
            $this->getConnection(),
            sprintf('SELECT id, body, headers FROM %s WHERE id = :id AND queue_name = :queue', $this->tableName),
            ['id' => (int)$id, 'queue' => $this->queueName],
        )));

        if (!is_array($row)) {
            return null;
        }

        return $this->decodeRow($row);
    }

    /**
     * Creates the table and its index if they do not exist yet.
     *
     * @return void
     */
    public function setup(): void
    {
        $this->guard(function (): void {
            $connection = $this->getConnection();

            foreach ($this->getSetupStatements() as $statement) {
                $connection->exec($statement);
            }
        });

        $this->setUp = true;
    }

    /**
     * @param \Symfony\Component\Messenger\Envelope $envelope
     *
     * @throws \Symfony\Component\Messenger\Exception\LogicException
     *
     * @return void
     */
    private function deleteMessage(Envelope $envelope): void
    {
        $stamp = $envelope->last(PropelReceivedStamp::class);

        if (!$stamp instanceof PropelReceivedStamp) {
            throw new LogicException('No PropelReceivedStamp found on the Envelope.');
        }

        $this->guard(fn () => $this->execute(
            $this->getConnection(),
            sprintf('DELETE FROM %s WHERE id = :id', $this->tableName),
            ['id' => $stamp->id],
        ));
    }

    /**
     * Runs a database operation, turning its failures into transport exceptions.
     *
     * When the operation fails because the table is missing, it creates the table and retries once.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @throws \Symfony\Component\Messenger\Exception\TransportException
     *
     * @return T
     */
    private function guard(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (PDOException | PropelExceptionInterface $exception) {
            if ($this->autoSetup && !$this->setUp && $this->isTableMissing($exception)) {
                $this->setup();

                return $this->guard($operation);
            }

            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param \Throwable $exception
     *
     * @return bool
     */
    private function isTableMissing(Throwable $exception): bool
    {
        for (; $exception !== null; $exception = $exception->getPrevious()) {
            if (!$exception instanceof PDOException) {
                continue;
            }

            // MySQL and PostgreSQL report a missing table by SQLSTATE, SQLite with a general error
            if (in_array($exception->errorInfo[0] ?? $exception->getCode(), ['42S02', '42P01'], true) || str_contains($exception->getMessage(), 'no such table')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $connection
     * @param \DateTimeImmutable $now
     * @param string $suffix
     *
     * @return \PDOStatement|\Propel\Runtime\Connection\StatementInterface
     */
    private function fetchAvailable(ConnectionInterface $connection, DateTimeImmutable $now, string $suffix)
    {
        return $this->execute(
            $connection,
            sprintf('SELECT id, body, headers FROM %s WHERE %s %s', $this->tableName, self::AVAILABLE_CONDITION, $suffix),
            $this->getAvailableParameters($now),
        );
    }

    /**
     * @param \Propel\Runtime\Connection\ConnectionInterface $connection
     * @param string $sql
     * @param array<string, int|string> $parameters
     *
     * @throws \Symfony\Component\Messenger\Exception\TransportException
     *
     * @return \PDOStatement|\Propel\Runtime\Connection\StatementInterface
     */
    private function execute(ConnectionInterface $connection, string $sql, array $parameters)
    {
        $statement = $connection->prepare($sql);

        if ($statement === false) {
            throw new TransportException('Could not prepare the Propel transport query.');
        }

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->execute();

        return $statement;
    }

    /**
     * Fetches the first row and releases the statement, which otherwise keeps SQLite from altering the table.
     *
     * @param \PDOStatement|\Propel\Runtime\Connection\StatementInterface $statement
     *
     * @return mixed
     */
    private function fetchRow(PDOStatement|StatementInterface $statement): mixed
    {
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return $row;
    }

    /**
     * Fetches the first value and releases the statement, which otherwise keeps SQLite from altering the table.
     *
     * @param \PDOStatement|\Propel\Runtime\Connection\StatementInterface $statement
     *
     * @return mixed
     */
    private function fetchValue(PDOStatement|StatementInterface $statement): mixed
    {
        $value = $statement->fetchColumn();
        $statement->closeCursor();

        return $value;
    }

    /**
     * @param \DateTimeImmutable $now
     *
     * @return array<string, string>
     */
    private function getAvailableParameters(DateTimeImmutable $now): array
    {
        return [
            'queue' => $this->queueName,
            'redeliver' => $now->modify(sprintf('-%d seconds', $this->redeliverTimeout))->format(self::SQL_DATE_FORMAT),
            'now' => $now->format(self::SQL_DATE_FORMAT),
        ];
    }

    /**
     * Locks the selected row against other workers, where the database supports it.
     *
     * @return string
     */
    private function getLockClause(): string
    {
        $adapter = Propel::getServiceContainer()->getAdapter($this->connectionName);

        return $adapter instanceof MysqlAdapter || $adapter instanceof PgsqlAdapter ? 'FOR UPDATE SKIP LOCKED' : '';
    }

    /**
     * @throws \Symfony\Component\Messenger\Exception\TransportException when the adapter is not supported
     *
     * @return list<string>
     */
    private function getSetupStatements(): array
    {
        $adapter = Propel::getServiceContainer()->getAdapter($this->connectionName);
        $index = sprintf('%s_queue_idx', $this->tableName);
        $indexColumns = '(queue_name, available_at, delivered_at, id)';

        if ($adapter instanceof MysqlAdapter) {
            return [
                sprintf('CREATE TABLE IF NOT EXISTS %s (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, PRIMARY KEY (id), INDEX %s %s) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB', $this->tableName, $index, $indexColumns),
            ];
        }

        if ($adapter instanceof PgsqlAdapter) {
            return [
                sprintf('CREATE TABLE IF NOT EXISTS %s (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP NOT NULL, available_at TIMESTAMP NOT NULL, delivered_at TIMESTAMP DEFAULT NULL, PRIMARY KEY (id))', $this->tableName),
                sprintf('CREATE INDEX IF NOT EXISTS %s ON %s %s', $index, $this->tableName, $indexColumns),
            ];
        }

        if ($adapter instanceof SqliteAdapter) {
            return [
                sprintf('CREATE TABLE IF NOT EXISTS %s (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP NOT NULL, available_at TIMESTAMP NOT NULL, delivered_at TIMESTAMP DEFAULT NULL)', $this->tableName),
                sprintf('CREATE INDEX IF NOT EXISTS %s ON %s %s', $index, $this->tableName, $indexColumns),
            ];
        }

        throw new TransportException(sprintf('The Propel transport cannot create its table with the %s adapter. Declare the "%s" table in your schema and set the "auto_setup" option to false.', $adapter::class, $this->tableName));
    }

    /**
     * @param array<mixed> $row
     *
     * @throws \Symfony\Component\Messenger\Exception\TransportException
     *
     * @return \Symfony\Component\Messenger\Envelope
     */
    private function decodeRow(array $row): Envelope
    {
        $id = $row['id'] ?? null;
        $body = $row['body'] ?? null;
        $headers = json_decode(is_string($row['headers'] ?? null) ? $row['headers'] : '', true);

        if (!is_numeric($id) || !is_string($body) || !is_array($headers)) {
            throw new TransportException('Invalid row in the Propel transport.');
        }

        $encodedEnvelope = ['body' => $body, 'headers' => $this->narrowHeaders($headers)];

        try {
            $envelope = $this->serializer->decode($encodedEnvelope);
        } catch (MessageDecodingFailedException $exception) {
            // Symfony 8 hands the failure to the worker, which rejects the message; Symfony 7.4 expects the transport to do so
            if (!method_exists(MessageDecodingFailedException::class, 'wrap')) {
                $this->deleteMessage(new Envelope($exception, [new PropelReceivedStamp((int)$id)]));

                throw $exception;
            }

            $envelope = MessageDecodingFailedException::wrap($encodedEnvelope, $exception->getMessage(), $exception->getCode(), $exception);
        }

        return $envelope
            ->withoutAll(TransportMessageIdStamp::class)
            ->with(new PropelReceivedStamp((int)$id), new TransportMessageIdStamp((int)$id));
    }

    /**
     * @param array<mixed> $headers
     *
     * @throws \Symfony\Component\Messenger\Exception\TransportException
     *
     * @return array<string, string>
     */
    private function narrowHeaders(array $headers): array
    {
        $stringHeaders = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new TransportException('Invalid headers in the Propel transport.');
            }

            $stringHeaders[$name] = $value;
        }

        return $stringHeaders;
    }

    /**
     * @return \DateTimeImmutable
     */
    private function getNow(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @return \Propel\Runtime\Connection\ConnectionInterface
     */
    private function getConnection(): ConnectionInterface
    {
        return Propel::getServiceContainer()->getWriteConnection($this->connectionName);
    }
}
