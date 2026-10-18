<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Tests\Form;

use PHPUnit\Framework\TestCase;
use Propel\Bundle\PropelBundle\Form\PropelExtension;
use Propel\Bundle\PropelBundle\Form\Type\ModelType;
use Propel\Bundle\PropelBundle\Form\Type\TranslationCollectionType;
use Propel\Bundle\PropelBundle\Form\Type\TranslationType;
use Propel\Bundle\PropelBundle\Form\TypeGuesser;

class PropelExtensionTest extends TestCase
{
    public function testTypesAndGuesser(): void
    {
        $extension = new PropelExtension();

        $this->assertTrue($extension->hasType(ModelType::class));
        $this->assertTrue($extension->hasType(TranslationCollectionType::class));
        $this->assertTrue($extension->hasType(TranslationType::class));
        $this->assertInstanceOf(TypeGuesser::class, $extension->getTypeGuesser());
    }
}
