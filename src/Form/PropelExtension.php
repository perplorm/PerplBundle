<?php

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
use Propel\Bundle\PropelBundle\Form\Type\TranslationCollectionType;
use Propel\Bundle\PropelBundle\Form\Type\TranslationType;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Represents the Propel form extension, which loads the Propel functionality.
 *
 * @author Joseph Rouff <rouffj@gmail.com>
 */
class PropelExtension extends AbstractExtension
{
 /**
  * @var \Symfony\Component\PropertyAccess\PropertyAccessorInterface
  */
    protected $propertyAccessor;

    /**
     * @var \Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface
     */
    protected $choiceListFactory;

    /**
     * @param \Symfony\Component\PropertyAccess\PropertyAccessorInterface|null $propertyAccessor
     * @param \Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface|null $choiceListFactory
     */
    public function __construct(?PropertyAccessorInterface $propertyAccessor = null, ?ChoiceListFactoryInterface $choiceListFactory = null)
    {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
        $this->choiceListFactory = $choiceListFactory ?: new PropertyAccessDecorator(new DefaultChoiceListFactory(), $this->propertyAccessor);
    }

    /**
     * @return array<\Symfony\Component\Form\FormTypeInterface<mixed>>
     */
    protected function loadTypes(): array
    {
        return [
            new ModelType($this->propertyAccessor, $this->choiceListFactory),
            new TranslationCollectionType(),
            new TranslationType(),
        ];
    }

    /**
     * @return \Symfony\Component\Form\FormTypeGuesserInterface|null
     */
    protected function loadTypeGuesser(): ?FormTypeGuesserInterface
    {
        return new TypeGuesser();
    }
}
