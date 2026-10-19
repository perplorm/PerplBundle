<?php

namespace ##NAMESPACE##;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ##CLASS## extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {##BUILD_CODE##
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => '##FQCN##',
        ]);
    }
}
