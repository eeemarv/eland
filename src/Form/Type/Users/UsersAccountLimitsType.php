<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersAccountLimitsCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersAccountLimitsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('min_limit', IntegerType::class);
        $builder->add('max_limit', IntegerType::class);
		$builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', UsersAccountLimitsCommand::class);
    }
}