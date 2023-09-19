<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersUsernameCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersUsernameType extends AbstractType
{
    public function __construct(
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('self_edit', CheckboxType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', UsersUsernameCommand::class);
    }
}