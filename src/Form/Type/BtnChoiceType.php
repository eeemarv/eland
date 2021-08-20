<?php declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class BtnChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'expanded'          => true,
            'multiple'          => false,
            'label_attr'        => [
                'class'     => 'radio-inline radio-custom',
            ],
        ]);
    }

    public function getParent():string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix():string
    {
        return 'btn_choice';
    }
}