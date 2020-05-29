<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class AccessType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'expanded'          => true,
            'multiple'          => false,
            'label_attr'        => [
                'class'     => 'radio-inline radio-custom',
            ],
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix()
    {
        return 'access';
    }
}