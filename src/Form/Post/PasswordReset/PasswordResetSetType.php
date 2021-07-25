<?php declare(strict_types=1);

namespace App\Form\Post\PasswordReset;

use App\Command\PasswordReset\PasswordResetSetCommand;
use App\Form\Input\PasswordResetAddonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PasswordResetSetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('password', PasswordResetAddonType::class)
			->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => PasswordResetSetCommand::class,
        ]);
    }
}