<?php declare(strict_types=1);

namespace App\Form\Type\Config;

use App\Command\Config\ConfigDateFormatCommand;
use App\Service\DateFormatService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigDateFormatType extends AbstractType
{
    public function __construct(
        protected DateFormatService $date_format_service
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder
            ->add('date_format', ChoiceType::class, [
                'choices'   => $this->date_format_service->get_choices(),
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', ConfigDateFormatCommand::class);
    }
}