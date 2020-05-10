<?php declare(strict_types=1);

namespace App\Form\Post;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\Input\EmailAddonType;

class ContactType extends AbstractType
{	
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('email', EmailAddonType::class, [
                'constraints' => new Assert\Email(),
            ])

            ->add('message', TextareaType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 20, 'max' => 2000])],
            ])

            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}