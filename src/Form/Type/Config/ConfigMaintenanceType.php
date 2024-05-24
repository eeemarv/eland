<?php declare(strict_types=1);

namespace App\Form\Type\Config;

use App\Command\Config\ConfigMaintenanceCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigMaintenanceType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder
            ->add('maintenance_en', CheckboxType::class)
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'data_class'    => ConfigMaintenanceCommand::class,
        ]);
    }
}