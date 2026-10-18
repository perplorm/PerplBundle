<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

declare(strict_types = 1);

namespace Propel\Bundle\PropelBundle\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Caster\TraceStub;
use function count;
use function debug_backtrace;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
class PropelLogger implements LoggerInterface
{
    use LoggerTrait;

    protected ?LoggerInterface $logger = null;

    /**
     * @var array<int, array{sql: string, connection: string, time: int|float, memory: int, trace: \Symfony\Component\VarDumper\Caster\TraceStub}>
     */
    protected array $queries = [];

    protected ?Stopwatch $stopwatch;

    private bool $isPrepared;

    /**
     * Constructor.
     *
     * @param \Psr\Log\LoggerInterface|null $logger A LoggerInterface instance
     * @param \Symfony\Component\Stopwatch\Stopwatch|null $stopwatch A Stopwatch instance
     */
    public function __construct(?LoggerInterface $logger = null, ?Stopwatch $stopwatch = null)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
        $this->isPrepared = false;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array<mixed> $context
     *
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        $add = true;
        $event = null;
        $trace = debug_backtrace();

        if ($this->stopwatch !== null) {
            $method = $trace[3]['function'];

            $watch = 'Propel Query ' . (count($this->queries) + 1);
            if ($method === 'prepare') {
                $this->isPrepared = true;
                $this->stopwatch->start($watch, 'propel');

                $add = false;
            } elseif ($this->isPrepared) {
                $this->isPrepared = false;
                $event = $this->stopwatch->stop($watch);
            }
        }

        // $trace[2] has no 'object' key if an exception is thrown while executing a query
        if ($add && $event !== null && isset($trace[2]['object'])) {
            $connection = $trace[2]['object'];

            $this->queries[] = [
                'sql' => $message,
                'connection' => $connection->getName(),
                'time' => $event->getDuration() / 1000,
                'memory' => $event->getMemory(),
                'trace' => new TraceStub($trace),
            ];
        }

        $this->logger->log($level, $message, $context);
    }

    /**
     * @return array<int, array{sql: string, connection: string, time: int|float, memory: int, trace: \Symfony\Component\VarDumper\Caster\TraceStub}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }
}
