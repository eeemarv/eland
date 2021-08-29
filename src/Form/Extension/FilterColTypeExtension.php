<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface as FormFormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilterColTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        yield TextType::class;
        yield ButtonType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('col', null);
        $resolver->setAllowedTypes('col', ['null', 'string']);
    }

    public function buildView(FormView $view, FormFormInterface $form, array $options): void
    {
        if (isset($options['col']))
        {
            $view->vars['col'] = $options['col'];
        }
    }
}
