<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use App\Form\Input\TextAddonType;
use Symfony\Component\Form\Extension\Core\Type\TelType;

class TelAddonType extends TextAddonType
{
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
//        $view->vars['type'] = 'tel';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'addon_fa'      => 'phone',
        ]);
    }

    public function getParent()
    {
        return TelType::class;
    }

    public function getBlockPrefix()
    {
        return 'addon';
    }
}