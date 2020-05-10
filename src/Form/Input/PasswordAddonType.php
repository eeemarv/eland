<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use App\Form\Input\TextAddonType;

class PasswordAddonType extends TextAddonType
{
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars['type'] = 'password';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'addon_fa'      => 'key',
        ]);
    }

    public function getParent()
    {
        return PasswordType::class;
    }

    public function getBlockPrefix()
    {
        return 'addon';
    }
}