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

namespace Propel\Bundle\PropelBundle\Form\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use function array_key_exists;
use function array_merge;
use function is_string;
use function strtoupper;

/**
 * Event Listener class for propel_translation
 *
 * @author Patrick Kaufmann
 */
class TranslationFormListener implements EventSubscriberInterface
{
    /**
     * @var array<string, array<string, mixed>|string|null>
     */
    private array $columns;

    private string $dataClass;

    /**
     * @param array<string, array<string, mixed>|string|null> $columns
     * @param string $dataClass
     */
    public function __construct(array $columns, string $dataClass)
    {
        $this->columns = $columns;
        $this->dataClass = $dataClass;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => ['preSetData', 1],
        ];
    }

    /**
     * @return void
     */
    public function preSetData(FormEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (!$data instanceof $this->dataClass) {
            return;
        }

        //loop over all columns and add the input
        foreach ($this->columns as $column => $options) {
            if (is_string($options)) {
                $column = $options;
                $options = [];
            }
            if ($options === null) {
                $options = [];
            }

            $type = TextType::class;
            if (array_key_exists('type', $options)) {
                $type = $options['type'];
            }
            $label = $column;
            if (array_key_exists('label', $options)) {
                $label = $options['label'];
            }

            $customOptions = [];
            if (array_key_exists('options', $options)) {
                $customOptions = $options['options'];
            }
            $options = [
                'label' => $label . ' ' . strtoupper($data->getLocale()),
            ];

            $options = array_merge($options, $customOptions);

            $form->add($column, $type, $options);
        }
    }
}
