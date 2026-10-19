<?php

namespace Propel\Bundle\PropelBundle\Tests\Functional;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Receives the default and a named connection through autowiring.
 */
class ConnectionConsumer
{
    public function __construct(
        public readonly ConnectionInterface $connection,
        public readonly ConnectionInterface $otherConnection,
    ) {
    }
}
