<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddonTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        yield TextType::class;
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('pre_addon', null);
        $resolver->setAllowedTypes('pre_addon', ['null', 'array']);
        $resolver->setDefault('post_addon', null);
        $resolver->setAllowedTypes('post_addon', ['null', 'array']);
    }

    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options
    ):void
    {
        if (isset($options['pre_addon']))
        {
            $view->vars['pre_addon'] = $options['pre_addon'];
        }

        if (isset($options['post_addon']))
        {
            $view->vars['post_addon'] = $options['post_addon'];
        }
    }
}
