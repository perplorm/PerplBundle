<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Messenger\Transport;

use SensitiveParameter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use function array_diff;
use function array_keys;
use function array_merge;
use function filter_var;
use function implode;
use function is_bool;
use function is_int;
use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function sprintf;
use function str_starts_with;
use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;
use const FILTER_VALIDATE_INT;
use const PHP_URL_HOST;
use const PHP_URL_QUERY;

/**
 * Creates Propel transports from DSNs like "propel://default?queue_name=failed".
 *
 * The host names the Propel connection. The options mirror those of Symfony's Doctrine transport.
 *
 * @implements \Symfony\Component\Messenger\Transport\TransportFactoryInterface<\Propel\Bundle\PropelBundle\Messenger\Transport\PropelTransport>
 */
final class PropelTransportFactory implements TransportFactoryInterface
{
    /**
     * @var string
     */
    private const SCHEME = 'propel://';

    /**
     * @var array<string, bool|int|string>
     */
    private const DEFAULT_OPTIONS = [
        'queue_name' => 'default',
        'redeliver_timeout' => 3600,
        'table_name' => 'messenger_message',
        'auto_setup' => true,
    ];

    /**
     * @param \Symfony\Component\Clock\ClockInterface $clock
     */
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    /**
     * @param string $dsn
     * @param array<mixed> $options
     * @param \Symfony\Component\Messenger\Transport\Serialization\SerializerInterface $serializer
     *
     * @throws \Symfony\Component\Messenger\Exception\InvalidArgumentException
     *
     * @return \Symfony\Component\Messenger\Transport\TransportInterface
     */
    public function createTransport(#[SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $connectionName = parse_url($dsn, PHP_URL_HOST);

        if (!is_string($connectionName) || $connectionName === '') {
            throw new InvalidArgumentException('The Propel transport DSN must name a connection, like "propel://default".');
        }

        $query = [];
        parse_str((string)parse_url($dsn, PHP_URL_QUERY), $query);
        unset($options['transport_name']);
        $options = array_merge(self::DEFAULT_OPTIONS, $query, $options);

        $unknownOptions = array_diff(array_keys($options), array_keys(self::DEFAULT_OPTIONS));

        if ($unknownOptions) {
            throw new InvalidArgumentException(sprintf('Unknown Propel transport option "%s".', implode('", "', $unknownOptions)));
        }

        $queueName = $options['queue_name'];
        $redeliverTimeout = filter_var($options['redeliver_timeout'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tableName = $options['table_name'];
        $autoSetup = filter_var($options['auto_setup'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if (!is_string($queueName) || $queueName === '') {
            throw new InvalidArgumentException('The Propel transport option "queue_name" must be a non-empty string.');
        }

        if (!is_int($redeliverTimeout)) {
            throw new InvalidArgumentException('The Propel transport option "redeliver_timeout" must be a positive integer.');
        }

        if (!is_string($tableName) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $tableName)) {
            throw new InvalidArgumentException('The Propel transport option "table_name" must be a plain SQL identifier.');
        }

        if (!is_bool($autoSetup)) {
            throw new InvalidArgumentException('The Propel transport option "auto_setup" must be a boolean.');
        }

        return new PropelTransport($serializer, $this->clock, $connectionName, $queueName, $redeliverTimeout, $tableName, $autoSetup);
    }

    /**
     * @param string $dsn
     * @param array<mixed> $options
     *
     * @return bool
     */
    public function supports(#[SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, self::SCHEME);
    }
}
