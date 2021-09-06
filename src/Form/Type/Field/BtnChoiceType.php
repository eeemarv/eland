<?php declare(strict_types=1);

namespace App\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;

class BtnChoiceType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        if (isset($options['count_ary']))
        {
            $view->vars['count_ary'] = $options['count_ary'];
        }
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('expanded', true);
        $resolver->setDefault('multiple', false);
        $resolver->setDefault('label_attr', function (Options $options) {
            if (isset($options['multiple']) && $options['multiple'] === true)
            {
                return [
                    'class' => 'checkbox-inline checkbox-custom',
                ];
            }

            return [
                'class' => 'radio-inline radio-custom',
            ];
        });
    }

    public function getParent():string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix():string
    {
        return 'btn_choice';
    }
}