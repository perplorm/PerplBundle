<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Util;

use Propel\Runtime\Map\ColumnMap;
use function method_exists;

/**
 * Reads the type of a column the same way for propel/propel and Perpl.
 *
 * @internal
 */
final class ColumnTypeName
{
    /**
     * Returns the type name, like "VARCHAR" or "ARRAY".
     *
     * Propel returns it as a string, Perpl as a ColumnType enum case with the same name.
     *
     * @param \Propel\Runtime\Map\ColumnMap $column
     *
     * @return string
     */
    public static function of(ColumnMap $column): string
    {
        if (method_exists($column, 'getColumnType')) {
            return $column->getColumnType()->name;
        }

        return $column->getType();
    }
}
