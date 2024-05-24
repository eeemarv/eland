<?php declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CountAryTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        yield ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('count_ary', null);
        $resolver->setAllowedTypes('count_ary', ['null', 'array']);
    }

    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options
    ):void
    {
        if (isset($options['count_ary']))
        {
            $view->vars['count_ary'] = $options['count_ary'];
        }
    }
}
