<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Attribute;

use Attribute;

/**
 * Configures how a controller argument is resolved to a model object.
 */
#[\Attribute(Attribute::TARGET_PARAMETER)]
final class MapModel
{
    /**
     * @param array<string, string>|null $mapping route parameter => column, replaces the default lookup by all route parameters
     * @param array<string> $exclude route parameters to ignore
     * @param array<string|array<string>>|string $with relations to populate, optionally with a join type (e.g. ['Book', 'left join'])
     * @param string|null $queryMethod query class method that receives the request attributes and returns the object
     */
    public function __construct(
        public readonly ?array $mapping = null,
        public readonly array $exclude = [],
        public readonly string|array $with = [],
        public readonly ?string $queryMethod = null,
    ) {
    }
}
