<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Service;

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Contracts\Service\ResetInterface;
use function method_exists;

/**
 * Empties the instance pools of all tables of the configured connections.
 *
 * Tagged `kernel.reset`, so long-running processes such as Messenger workers start every message, or request, without
 * the objects loaded by the previous one. Pooling itself stays enabled.
 */
final class InstancePoolResetter implements ResetInterface
{
    /**
     * @param array<string> $connectionNames
     */
    public function __construct(private readonly array $connectionNames)
    {
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        $serviceContainer = Propel::getServiceContainer();

        foreach ($this->connectionNames as $name) {
            try {
                $databaseMap = $serviceContainer->getDatabaseMap($name);
            } catch (PropelException) {
                // no database map was loaded, so nothing is pooled
                continue;
            }

            foreach ($databaseMap->getTables() as $tableMap) {
                // generated table maps pool their objects, a plain TableMap does not
                if (method_exists($tableMap, 'clearInstancePool')) {
                    $tableMap::clearInstancePool();
                }
            }
        }
    }
}
