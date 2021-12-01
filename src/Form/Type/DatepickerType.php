<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Form\DataTransformer\DatepickerTransformer;
use App\Service\DateFormatService;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class DatepickerType extends AbstractType
{
    protected DateFormatService $date_format_service;
    protected PageParamsService $pp;
    protected DatepickerTransformer $datepicker_transformer;

    public function __construct(
        DatepickerTransformer $datepicker_transformer,
        DateFormatService $date_format_service,
        PageParamsService $pp
    )
    {
        $this->datepicker_transformer = $datepicker_transformer;
        $this->date_format_service = $date_format_service;
        $this->pp = $pp;
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    )
    {
        $builder->addModelTransformer($this->datepicker_transformer);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars['attr_translation_parameters'] = array_merge([
            'var' => $this->date_format_service->datepicker_placeholder($this->pp->schema()),
        ], $options['attr_translation_parameters']);

        $view->vars['attr'] = array_merge([
            'data-date-format'  => $this->date_format_service->datepicker_format($this->pp->schema()),
            'placeholder'       => 'var',
        ], $options['attr']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'datepicker';
    }
}