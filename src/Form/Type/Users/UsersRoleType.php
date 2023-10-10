<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersRoleCommand;
use App\Form\Type\Field\BtnChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersRoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('role', BtnChoiceType::class, [
            'choices'   => [
                'user_role'   => 'user',
                'admin_role'  => 'admin',
            ],
        ]);

		$builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', UsersRoleCommand::class);
    }
}