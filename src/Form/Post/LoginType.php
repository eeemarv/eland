<?php declare(strict_types=1);

namespace App\Form\Post;

use App\Command\Auth\LoginCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\Input\TextAddonType;
use App\Form\Input\PasswordAddonType;

class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('login', TextAddonType::class)
            ->add('password', PasswordAddonType::class)
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => LoginCommand::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'login_form';
    }
}