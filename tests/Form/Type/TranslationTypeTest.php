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
use Propel\Bundle\PropelBundle\Form\EventListener\TranslationFormListener;
use Propel\Bundle\PropelBundle\Form\Type\TranslationType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TranslationTypeTest extends TestCase
{
    public function testBuildFormAddsTheListener(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->once())
            ->method('addEventSubscriber')
            ->with($this->isInstanceOf(TranslationFormListener::class));

        (new TranslationType())->buildForm($builder, [
            'columns' => ['title' => 'title'],
            'data_class' => TranslationTypeTranslation::class,
        ]);
    }

    public function testOptions(): void
    {
        $type = new TranslationType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([
            'data_class' => TranslationTypeTranslation::class,
            'columns' => ['title'],
        ]);

        $this->assertSame(TranslationTypeTranslation::class, $options['data_class']);
        $this->assertSame(['title'], $options['columns']);
        $this->assertSame('propel_translation', $type->getBlockPrefix());

        $this->expectException(MissingOptionsException::class);
        $resolver->resolve(['data_class' => TranslationTypeTranslation::class]);
    }

    public function testListenerAddsAFieldPerColumn(): void
    {
        $addedFields = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $type, array $options) use (&$addedFields, $form): FormInterface {
            $addedFields[] = [$name, $type, $options];

            return $form;
        });

        $listener = new TranslationFormListener([
            'summary' => null,
            'headline' => 'title',
            'details' => [
                'type' => TextareaType::class,
                'label' => 'Description',
                'options' => ['required' => false],
            ],
        ], TranslationTypeTranslation::class);

        $listener->preSetData(new FormEvent($form, new TranslationTypeTranslation()));

        $this->assertSame([
            ['summary', TextType::class, ['label' => 'summary FR']],
            ['title', TextType::class, ['label' => 'title FR']],
            ['details', TextareaType::class, ['label' => 'Description FR', 'required' => false]],
        ], $addedFields);
        $this->assertSame([FormEvents::PRE_SET_DATA => ['preSetData', 1]], TranslationFormListener::getSubscribedEvents());
    }

    public function testListenerIgnoresOtherData(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects($this->never())->method('add');

        $listener = new TranslationFormListener(['title' => 'title'], TranslationTypeTranslation::class);
        $listener->preSetData(new FormEvent($form, null));
        $listener->preSetData(new FormEvent($form, new \stdClass()));
    }
}

class TranslationTypeTranslation
{
    public function getLocale(): string
    {
        return 'fr';
    }
}
