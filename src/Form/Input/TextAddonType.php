<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class TextAddonType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (isset($options['addon_fa'])) 
        {
            $view->vars['addon_fa'] = $options['addon_fa'];
        }

        if (isset($options['addon_label'])) 
        {
            $view->vars['addon_label'] = $options['addon_label'];
        }

        if (isset($options['addon_class']))
        {
            $view->vars['addon_class'] = $options['addon_class'];
        }
    }    

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'addon_fa'      => null,
            'addon_label'   => null,
            'addon_class'   => null,
        ]);
    }

    public function getParent()
    {
        return TextType::class;
    }

    public function getBlockPrefix()
    {
        return 'addon';
    }
}