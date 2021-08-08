<?php declare(strict_types=1);

namespace App\Form\Post\RegisterForm;

use App\Command\RegisterForm\RegisterFormCommand;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Gregwar\CaptchaBundle\Type\CaptchaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class RegisterFormType extends AbstractType
{
    public function __construct(
        protected ConfigService $config_service,
        protected PageParamsService $pp
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $postcode_enabled = $this->config_service->get_bool('users.fields.postcode.enabled', $this->pp->schema());

        $builder->add('email', EmailType::class);
        $builder->add('first_name', TextType::class);
        $builder->add('last_name', TextType::class);

        if ($postcode_enabled)
        {
            $builder->add('postcode', TextType::class);
        }

        $builder->add('mobile', TelType::class);
        $builder->add('phone', TelType::class);
        $builder->add('captcha', CaptchaType::class);
/*
            ->add('accept', CheckboxType::class, [
                'constraints' => new Assert\IsTrue(),
            ])
*/
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => RegisterFormCommand::class,
        ]);
    }
}