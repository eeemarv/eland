<?php declare(strict_types=1);

namespace App\Form\Type\PasswordReset;

use App\Command\PasswordReset\PasswordResetCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class PasswordResetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('email', EmailType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', PasswordResetCommand::class);
    }
}