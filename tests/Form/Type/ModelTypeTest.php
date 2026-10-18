<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Tests\Form\Type;

use PHPUnit\Framework\TestCase;
use Propel\Bundle\PropelBundle\Form\ChoiceList\PropelChoiceLoader;
use Propel\Bundle\PropelBundle\Form\DataTransformer\CollectionToArrayTransformer;
use Propel\Bundle\PropelBundle\Form\Type\ModelType;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ModelTypeTest extends TestCase
{
    public function testOptions(): void
    {
        $type = new ModelType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([
            'class' => ModelTypeChoice::class,
            'query' => $this->createQuery(\PDO::PARAM_INT),
            'property' => 'name',
        ]);

        $this->assertSame('model', $type->getBlockPrefix());
        $this->assertSame(ChoiceType::class, $type->getParent());
        $this->assertSame([ModelType::class, 'createChoiceName'], $options['choice_name']);
        $this->assertFalse($options['choice_translation_domain']);
        $this->assertFalse($options['by_reference']);
        $this->assertInstanceOf(PropelChoiceLoader::class, $options['choice_loader']);

        $book = new ModelTypeChoice(42, 'A book');

        $this->assertSame('A_book', ModelType::createChoiceName($book, 'ignored', 'A-book'));
        $this->assertSame('A book', $options['choice_label']($book));
        $this->assertSame(42, $options['choice_value']($book));
        $this->assertNull($options['choice_value'](null));
    }

    public function testStringIdentifierHasNoChoiceName(): void
    {
        $resolver = new OptionsResolver();
        (new ModelType())->configureOptions($resolver);

        $options = $resolver->resolve([
            'class' => ModelTypeChoice::class,
            'query' => $this->createQuery(\PDO::PARAM_STR),
        ]);

        $this->assertNull($options['choice_name']);
        // Without a property, the label is the string representation of the model
        $this->assertSame('#42 A book', $options['choice_label'](new ModelTypeChoice(42, 'A book')));
    }

    public function testExplicitChoicesDisableTheLoader(): void
    {
        $resolver = new OptionsResolver();
        (new ModelType())->configureOptions($resolver);

        $options = $resolver->resolve([
            'class' => ModelTypeChoice::class,
            'query' => $this->createQuery(\PDO::PARAM_INT),
            'choices' => [],
        ]);

        $this->assertNull($options['choice_loader']);
    }

    public function testEmptyClass(): void
    {
        $resolver = new OptionsResolver();
        (new ModelType())->configureOptions($resolver);

        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('class');
        $resolver->resolve(['class' => '']);
    }

    public function testUnknownClass(): void
    {
        $resolver = new OptionsResolver();
        (new ModelType())->configureOptions($resolver);

        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('App\Missing\ModelQuery');
        $resolver->resolve(['class' => 'App\\Missing\\Model']);
    }

    public function testMultipleAddsTheCollectionTransformer(): void
    {
        $type = new ModelType();

        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->once())
            ->method('addViewTransformer')
            ->with($this->isInstanceOf(CollectionToArrayTransformer::class), true);
        $type->buildForm($builder, ['multiple' => true]);

        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->never())->method('addViewTransformer');
        $type->buildForm($builder, ['multiple' => false]);
    }

    private function createQuery(int $pdoType): ModelCriteria
    {
        $column = $this->createStub(ColumnMap::class);
        $column->method('getPdoType')->willReturn($pdoType);
        $column->method('getPhpName')->willReturn('Id');

        $tableMap = $this->createStub(TableMap::class);
        $tableMap->method('getPrimaryKeys')->willReturn([$column]);

        $query = $this->createStub(ModelCriteria::class);
        $query->method('getTableMap')->willReturn($tableMap);

        return $query;
    }
}

class ModelTypeChoice
{
    public function __construct(private int $id, private string $name)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return sprintf('#%d %s', $this->id, $this->name);
    }
}
