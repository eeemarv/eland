<?php declare(strict_types=1);

namespace App\Form\Post;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\Input\TextAddonType;
use App\Form\Input\EmailAddonType;

class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('first_name', TextAddonType::class)
            ->add('last_name', TextAddonType::class)
            ->add('email', EmailAddonType::class, [
                'constraints' => new Assert\Email(),
            ])
            ->add('postcode', TextAddonType::class)
            ->add('mobile', TextAddonType::class, [
                'required'	=> false,
            ])
            ->add('telephone', TextAddonType::class, [
                'required'	=> false,
            ])
            ->add('accept', CheckboxType::class, [
                'constraints' => new Assert\IsTrue(),
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}