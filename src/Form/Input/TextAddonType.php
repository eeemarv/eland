<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class TextAddonType extends AbstractType
{
    const DEFAULTS = [
        'addon_fa'      => null,
        'addon_label'   => null,
        'addon_html'    => false,
        'addon_class'   => null,
        'addon_translation_parameters'  => [],
    ];

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        foreach (self::DEFAULTS as $key => $default)
        {
            if (!isset($options[$key]))
            {
                continue;
            }

            $view->vars[$key] = $options[$key];
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(self::DEFAULTS);
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