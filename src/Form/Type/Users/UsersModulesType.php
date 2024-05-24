<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersModulesCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersModulesType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder->add('full_name_enabled', CheckboxType::class);
        $builder->add('postcode_enabled', CheckboxType::class);
        $builder->add('birthday_enabled', CheckboxType::class);
        $builder->add('hobbies_enabled', CheckboxType::class);
        $builder->add('comments_enabled', CheckboxType::class);
        $builder->add('admin_comments_enabled', CheckboxType::class);
        $builder->add('new_enabled', CheckboxType::class);
        $builder->add('leaving_enabled', CheckboxType::class);
        $builder->add('intersystem_enabled', CheckboxType::class);
        $builder->add('periodic_mail_enabled', CheckboxType::class);
        $builder->add('mollie_enabled', CheckboxType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'data_class'    => UsersModulesCommand::class,
        ]);
    }
}