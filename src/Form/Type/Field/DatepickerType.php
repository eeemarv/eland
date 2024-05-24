<?php declare(strict_types=1);

namespace App\Form\Type\Field;

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
    public function __construct(
        protected DatepickerTransformer $datepicker_transformer,
        protected DateFormatService $date_format_service,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder->addModelTransformer($this->datepicker_transformer);
    }

    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options
    ):void
    {
        parent::buildView($view, $form, $options);

        $view->vars['attr_translation_parameters'] = [
            'var' => $this->date_format_service->datepicker_placeholder($this->pp->schema()),
            ...$options['attr_translation_parameters'],
        ];

        $view->vars['attr'] = [
            'data-date-format'  => $this->date_format_service->datepicker_format($this->pp->schema()),
            'placeholder'       => 'var',
            ...$options['attr'],
        ];
    }

    public function configureOptions(OptionsResolver $resolver):void
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