<?php declare(strict_types=1);

namespace App\Form\Input;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class PasswordResetAddonType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $keys = [
            'addon_fa',
            'addon_label',
            'addon_class',
            'addon_btn_fa',
            'addon_btn_label',
            'addon_btn_title',
            'addon_btn_class',
            'addon_btn_attr',
        ];

        foreach ($keys as $key)
        {
            if (isset($options[$key]))
            {
                $view->vars[$key] = $options[$key];
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label'             => 'label.password',
            'addon_fa'          => 'key',
            'addon_label'       => null,
            'addon_class'       => null,
            'addon_btn_fa'      => null,
            'addon_btn_label'   => 'btn.generate',
            'addon_btn_title'   => 'btn.generate_password_title',
            'addon_btn_class'   => 'btn-default border border-secondary-li',
            'addon_btn_attr'    => [
                'data-generate-password'    => '',
            ],
            'attr'              => [
                'data-generate-password-target'     => '',
            ]
        ]);
    }

    public function getParent()
    {
        return TextType::class;
    }

    public function getBlockPrefix()
    {
        return 'addon_button';
    }
}