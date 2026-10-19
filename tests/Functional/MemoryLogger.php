<?php

namespace Propel\Bundle\PropelBundle\Tests\Functional;

use Psr\Log\AbstractLogger;

/**
 * Keeps the log records in memory for the tests to inspect.
 */
class MemoryLogger extends AbstractLogger
{
    /**
     * @var array<int, array{level: mixed, message: string, context: array<mixed>}>
     */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
