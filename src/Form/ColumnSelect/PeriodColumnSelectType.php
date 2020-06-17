<?php declare(strict_types=1);

namespace App\Form\ColumnSelect;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use form\input\datepicker_type;
use App\Form\Input\DatepickerType;

class PeriodColumnSelectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('from', DatepickerType::class, [
                'required'  => false,
                'attr'      => [
                    'data-date-default-view-date'   => '-1y',
                    'data-date-end-date'            => '0d',
                ]
            ])
            ->add('to', DatepickerType::class, [
                'required'  => false,
                'attr'      => [
                    'data-date-end-date'    => '0d',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}