<?php declare(strict_types=1);

namespace App\Form\Post\Register;

use App\Command\Register\RegisterCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\Input\EmailAddonType;
use App\Form\Input\TelAddonType;
use App\Form\Input\TextAddonType;
use Gregwar\CaptchaBundle\Type\CaptchaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailAddonType::class)
            ->add('first_name', TextAddonType::class)
            ->add('last_name', TextAddonType::class)
            ->add('postcode', TextAddonType::class)
            ->add('mobile', TelAddonType::class)
            ->add('phone', TelAddonType::class)
            ->add('captcha', CaptchaType::class)
/*
            ->add('accept', CheckboxType::class, [
                'constraints' => new Assert\IsTrue(),
            ])
*/
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => RegisterCommand::class,
        ]);
    }
}