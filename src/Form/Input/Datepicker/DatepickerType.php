<?php declare(strict_types=1);

namespace App\Form\Input\Datepicker;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class DatepickerType extends AbstractType
{
    public function __construct(
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    )
    {
 //       $builder->addModelTransformer($this->html_purify_transformer);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars['attr'] = $options['attr'];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }

    public function getParent()
    {
        return DateType::class;
    }

    public function getBlockPrefix()
    {
        return 'datepicker';
    }
}