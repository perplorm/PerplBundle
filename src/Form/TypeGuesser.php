<?php

declare(strict_types = 1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Bundle\PropelBundle\Form;

use Propel\Bundle\PropelBundle\Form\Type\ModelType;
use Propel\Bundle\PropelBundle\Util\ColumnTypeName;
use Propel\Runtime\Map\RelationMap;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use function array_combine;
use function array_map;
use function class_exists;
use function strtolower;

/**
 * Propel Type guesser.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TypeGuesser implements FormTypeGuesserInterface
{
    /**
     * @var array<string, \Propel\Runtime\Map\TableMap|\Propel\Runtime\Map\ColumnMap>
     */
    private array $cache = [];

    /**
     * {@inheritDoc}
     *
     * @param string $class
     * @param string $property
     */
    public function guessType($class, $property): ?TypeGuess
    {
        $table = $this->getTable($class);
        if (!$table) {
            return new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE);
        }

        foreach ($table->getRelations() as $relation) {
            if ($relation->getType() === RelationMap::MANY_TO_ONE) {
                if (strtolower($property) === strtolower($relation->getName())) {
                    return new TypeGuess(ModelType::class, [
                        'class' => $relation->getForeignTable()->getClassName(),
                        'multiple' => false,
                    ], Guess::HIGH_CONFIDENCE);
                }
            } elseif ($relation->getType() === RelationMap::ONE_TO_MANY) {
                if (strtolower($property) === strtolower($relation->getPluralName())) {
                    return new TypeGuess(ModelType::class, [
                        'class' => $relation->getForeignTable()->getClassName(),
                        'multiple' => true,
                    ], Guess::HIGH_CONFIDENCE);
                }
            } elseif ($relation->getType() === RelationMap::MANY_TO_MANY) {
                if (strtolower($property) == strtolower($relation->getPluralName())) {
                    return new TypeGuess(ModelType::class, [
                        'class' => $relation->getLocalTable()->getClassName(),
                        'multiple' => true,
                    ], Guess::HIGH_CONFIDENCE);
                }
            }
        }

        $column = $this->getColumn($class, $property);
        if (!$column) {
            return new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE);
        }

        switch (ColumnTypeName::of($column)) {
            case 'BOOLEAN':
            case 'BOOLEAN_EMU':
                return new TypeGuess(CheckboxType::class, [], Guess::HIGH_CONFIDENCE);
            case 'TIMESTAMP':
            case 'BU_TIMESTAMP':
                return new TypeGuess(DateTimeType::class, [], Guess::HIGH_CONFIDENCE);
            case 'DATE':
            case 'BU_DATE':
                return new TypeGuess(DateType::class, [], Guess::HIGH_CONFIDENCE);
            case 'TIME':
                return new TypeGuess(TimeType::class, [], Guess::HIGH_CONFIDENCE);
            case 'FLOAT':
            case 'REAL':
            case 'DOUBLE':
            case 'DECIMAL':
                return new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE);
            case 'TINYINT':
            case 'SMALLINT':
            case 'INTEGER':
            case 'BIGINT':
            case 'NUMERIC':
                return new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE);
            case 'ENUM':
            case 'CHAR':
                if ($column->getValueSet()) {
                    //check if this is mysql enum
                    $choices = $column->getValueSet();
                    $labels = array_map('ucfirst', $choices);

                    return new TypeGuess(ChoiceType::class, ['choices' => array_combine($choices, $labels)], Guess::MEDIUM_CONFIDENCE);
                }
                // no break
            case 'VARCHAR':
                return new TypeGuess(TextType::class, [], Guess::MEDIUM_CONFIDENCE);
            case 'LONGVARCHAR':
            case 'BLOB':
            case 'CLOB':
            case 'CLOB_EMU':
                return new TypeGuess(TextareaType::class, [], Guess::MEDIUM_CONFIDENCE);
            default:
                return new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param string $class
     * @param string $property
     */
    public function guessRequired($class, $property): ?ValueGuess
    {
        $column = $this->getColumn($class, $property);
        if ($column) {
            return new ValueGuess($column->isNotNull(), Guess::HIGH_CONFIDENCE);
        }

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $class
     * @param string $property
     */
    public function guessMaxLength($class, $property): ?ValueGuess
    {
        $column = $this->getColumn($class, $property);
        if ($column) {
            if ($column->isText()) {
                return new ValueGuess($column->getSize(), Guess::HIGH_CONFIDENCE);
            }
            switch (ColumnTypeName::of($column)) {
                case 'FLOAT':
                case 'REAL':
                case 'DOUBLE':
                case 'DECIMAL':
                    return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $class
     * @param string $property
     */
    public function guessPattern($class, $property): ?ValueGuess
    {
        $column = $this->getColumn($class, $property);
        if ($column) {
            switch (ColumnTypeName::of($column)) {
                case 'FLOAT':
                case 'REAL':
                case 'DOUBLE':
                case 'DECIMAL':
                    return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
            }
        }

        return null;
    }

    /**
     * @param string $class
     *
     * @return \Propel\Runtime\Map\TableMap|null
     */
    protected function getTable(string $class)
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $queryClass = $class . 'Query';
        if (class_exists($queryClass)) {
            $query = new $queryClass();

            return $this->cache[$class] = $query->getTableMap();
        }

        return null;
    }

    /**
     * @param string $class
     * @param string $property
     *
     * @return \Propel\Runtime\Map\ColumnMap|null
     */
    protected function getColumn(string $class, string $property)
    {
        if (isset($this->cache[$class . '::' . $property])) {
            return $this->cache[$class . '::' . $property];
        }

        $table = $this->getTable($class);

        if ($table && $table->hasColumn($property)) {
            return $this->cache[$class . '::' . $property] = $table->getColumn($property);
        }

        return null;
    }
}
