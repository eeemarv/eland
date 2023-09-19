<?php declare(strict_types=1);

namespace App\Form\Type\ContactForm;

use App\Command\ContactForm\ContactFormCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Gregwar\CaptchaBundle\Type\CaptchaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('email', EmailType::class);
        $builder->add('message', TextareaType::class);
        $builder->add('captcha', CaptchaType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', ContactFormCommand::class);
    }
}