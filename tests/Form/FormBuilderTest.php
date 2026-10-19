<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Tests\Form;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Propel\Bundle\PropelBundle\Form\FormBuilder;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Table;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function class_exists;
use function file_put_contents;
use function is_subclass_of;
use function uniqid;

class FormBuilderTest extends TestCase
{
    /**
     * @return void
     */
    public function testBuildFormType(): void
    {
        $generated = $this->buildBookFormType('App');

        $this->assertStringContainsString('namespace App\\Form\\Type;', $generated);
        $this->assertStringContainsString('class BookType extends AbstractType', $generated);
        $this->assertStringContainsString("'data_class' => 'App\\Model\\Book'", $generated);
        $this->assertStringContainsString("\$builder->add('title');", $generated);
        $this->assertStringContainsString("\$builder->add('author');", $generated);
        $this->assertStringNotContainsString("\$builder->add('id');", $generated);
        $this->assertStringNotContainsString('##', $generated);
    }

    /**
     * @return void
     */
    public function testGeneratedFormTypeLoads(): void
    {
        // a unique namespace, so that the generated class is never declared twice
        $namespace = uniqid('GeneratedFormTypeTest');
        $generated = $this->buildBookFormType($namespace);

        vfsStream::setup('generated');
        $file = vfsStream::url('generated/BookType.php');
        file_put_contents($file, $generated);
        require $file;

        $class = $namespace . '\\Form\\Type\\BookType';
        $this->assertTrue(class_exists($class, false));
        $this->assertTrue(is_subclass_of($class, AbstractType::class));

        $resolver = new OptionsResolver();
        (new $class())->configureOptions($resolver);

        $this->assertSame(['data_class' => 'App\\Model\\Book'], $resolver->resolve([]));
    }

    /**
     * @param string $bundleNamespace
     *
     * @return string
     */
    protected function buildBookFormType(string $bundleNamespace): string
    {
        $primaryKey = $this->createStub(Column::class);
        $primaryKey->method('isPrimaryKey')->willReturn(true);

        $title = $this->createStub(Column::class);
        $title->method('isPrimaryKey')->willReturn(false);
        $title->method('isForeignKey')->willReturn(false);
        $title->method('getPhpName')->willReturn('Title');

        $foreignTable = $this->createStub(Table::class);
        $foreignTable->method('getPhpName')->willReturn('Author');
        $foreignKey = $this->createStub(ForeignKey::class);
        $foreignKey->method('getForeignTable')->willReturn($foreignTable);
        $authorId = $this->createStub(Column::class);
        $authorId->method('isPrimaryKey')->willReturn(false);
        $authorId->method('isForeignKey')->willReturn(true);
        $authorId->method('getPhpName')->willReturn('AuthorId');
        $authorId->method('getForeignKeys')->willReturn([$foreignKey]);

        $table = $this->createStub(Table::class);
        $table->method('getPhpName')->willReturn('Book');
        $table->method('getNamespace')->willReturn('App\\Model');
        $table->method('getColumns')->willReturn([$primaryKey, $title, $authorId]);

        $bundle = $this->createStub(BundleInterface::class);
        $bundle->method('getNamespace')->willReturn($bundleNamespace);

        return (new FormBuilder())->buildFormType($bundle, $table, '/Form/Type');
    }
}
