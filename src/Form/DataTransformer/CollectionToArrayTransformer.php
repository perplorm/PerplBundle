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

namespace Propel\Bundle\PropelBundle\Form\DataTransformer;

use Propel\Runtime\Collection\ObjectCollection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use function is_array;

/**
 * CollectionToArrayTransformer class.
 *
 * @author William Durand <william.durand1@gmail.com>
 * @author Pierre-Yves Lebecq <py.lebecq@gmail.com>
 *
 * @implements \Symfony\Component\Form\DataTransformerInterface<\Propel\Runtime\Collection\ObjectCollection<mixed>, array<mixed>>
 */
class CollectionToArrayTransformer implements DataTransformerInterface
{
    /**
     * @param mixed $collection
     *
     * @throws \Symfony\Component\Form\Exception\TransformationFailedException
     *
     * @return array<mixed>
     */
    public function transform(mixed $collection): array
    {
        if ($collection === null) {
            return [];
        }

        if (!$collection instanceof ObjectCollection) {
            throw new TransformationFailedException('Expected a \Propel\Runtime\Collection\ObjectCollection.');
        }

        return $collection->getData();
    }

    /**
     * @param array<mixed>|string|null $array
     *
     * @throws \Symfony\Component\Form\Exception\TransformationFailedException
     *
     * @return \Propel\Runtime\Collection\ObjectCollection<mixed>
     */
    public function reverseTransform(mixed $array): ObjectCollection
    {
        $collection = new ObjectCollection();

        if ($array === '' || $array === null) {
            return $collection;
        }

        if (!is_array($array)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $collection->setData($array);

        return $collection;
    }
}
