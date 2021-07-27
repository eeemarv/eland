<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface as FormFormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddonTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [
            TextType::class,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined([
            'pre_addon',
            'post_addon',
        ]);
    }

    public function buildView(FormView $view, FormFormInterface $form, array $options): void
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
