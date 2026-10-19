<?php

namespace Propel\Bundle\PropelBundle\Tests\Messenger\Transport;

use PDOException;
use Propel\Runtime\Connection\ConnectionWrapper;

/**
 * A connection reporting a locked database for a given number of operations before working normally.
 */
class FlakyConnection extends ConnectionWrapper
{
    public int $failures = 0;

    public function transaction(callable $callable)
    {
        $this->failWhenAsked();

        return parent::transaction($callable);
    }

    public function prepare(string $statement, array $driverOptions = [])
    {
        $this->failWhenAsked();

        return parent::prepare($statement, $driverOptions);
    }

    private function failWhenAsked(): void
    {
        if ($this->failures > 0) {
            $this->failures--;

            throw new PDOException('SQLSTATE[HY000]: General error: 5 database is locked');
        }
    }
}
