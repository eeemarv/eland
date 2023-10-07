<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersPeriodicOverviewCommand;
use App\Form\Type\Field\BtnChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersPeriodicOverviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('enabled', BtnChoiceType::class, [
            'choices'   => [
                'enabled'   => true,
                'disabled'  => false,
            ],
        ]);
		$builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', UsersPeriodicOverviewCommand::class);
    }
}