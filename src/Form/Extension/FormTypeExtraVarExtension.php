<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormTypeExtraVarExtension extends AbstractTypeExtension
{
    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options
    ):void
    {
        if (isset($options['explain']))
        {
            $view->vars['explain'] = $options['explain'];
        }

        if (isset($options['sub_label']))
        {
             $view->vars['sub_label'] = $options['sub_label'];
        }
    }

    public function configureOptions(
        OptionsResolver $resolver
    ):void
    {
        $resolver->setDefined([
            'explain',
            'sub_label'
        ]);
    }

    public static function getExtendedTypes():iterable
    {
        yield FormType::class;
    }
}
