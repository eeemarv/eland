<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class SelectAddonType extends AbstractType
{
    const DEFAULTS = [
        'addon_fa'                      => null,
        'addon_label'                   => null,
        'addon_class'                   => null,
        'addon_html'                    => false,
        'addon_translation_parameters'  => [],
        'expanded'                      => false,
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
        return ChoiceType::class;
    }

    public function getBlockPrefix()
    {
        return 'select_addon';
    }
}