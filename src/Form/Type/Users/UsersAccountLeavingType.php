<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersAccountLeavingCommand;
use App\Form\Type\Field\BtnChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersAccountLeavingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('is_leaving', BtnChoiceType::class, [
            'choices'   => [
                'account_not_leaving'   => false,
                'account_leaving'       => true,
            ]
        ]);
		$builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', UsersAccountLeavingCommand::class);
    }
}