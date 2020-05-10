<?php declare(strict_types=1);

namespace App\Form\Post;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\DBAL\Connection as db;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\Input\TextAddonType;
use App\Form\Input\PasswordAddonType;

class LoginType extends AbstractType
{	

    public function __construct(
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('login', TextAddonType::class, [			
                'constraints' 	=> [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 100, 'min' => 10]),
                ],
                'attr'	=> [
                    'maxlength'	=> 100,
                ],
            ])

            ->add('password', PasswordAddonType::class, [
                'constraints' 	=> [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 100, 'min' => 4]),
                ],
                'attr'	=> [
                    'maxlength'	=> 100,
                    'minlength' => 4,
                ],
            ])

            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([

        ]);
    }

    public function getBlockPrefix()
    {
        return null;
    }
}