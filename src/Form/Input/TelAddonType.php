<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use App\Form\Input\TextAddonType;
use Symfony\Component\Form\Extension\Core\Type\TelType;

class TelAddonType extends TextAddonType
{
    const DEFAULTS = [
        'addon_fa'      => 'phone',
        'addon_label'   => null,
        'addon_html'    => false,
        'addon_class'   => null,
        'addon_translation_parameters'  => [],
    ];

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars['type'] = 'tel';

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
        return TelType::class;
    }

    public function getBlockPrefix()
    {
        return 'addon';
    }
}