<?php declare(strict_types=1);

namespace App\Form\Type\UsersConfig;

use App\Command\UsersConfig\UsersConfigPeriodicMailCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersConfigPeriodicMailType extends AbstractType
{
    public function __construct(
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('days', IntegerType::class);
        $builder->add('block_layout', HiddenType::class);
        $builder->add('block_select_options', HiddenType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', UsersConfigPeriodicMailCommand::class);
    }
}