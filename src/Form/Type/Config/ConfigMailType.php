<?php declare(strict_types=1);

namespace App\Form\Type\Config;

use App\Command\Config\ConfigMailCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigMailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('enabled', CheckboxType::class);
        $builder->add('tag', TextType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', ConfigMailCommand::class);
    }
}