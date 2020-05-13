<?php declare(strict_types=1);

namespace App\Form\Post;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Form\Input\TextAddonType;
use App\Form\Input\EmailAddonType;
use App\Form\Input\BadgeChoiceType;
use App\Form\Input\DatepickerType;

class NewsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('itemdate', DatepickerType::class)
            ->add('sticky', CheckboxType::class, [
                'required'  => false,
            ])
            ->add('location', TextAddonType::class, [
                'constraints' => [
                    new Assert\Length(['max' => 128]),
                ],
                'required'  => false,
            ])
            ->add('headline', TextAddonType::class, [
                'constraints' => [
                    new Assert\Length(['max' => 200]),
                ],
            ])
            ->add('newsitem', TextareaType::class, [
                'constraints' => [
                    new Assert\Length(['max' => 4000]),
                ],
            ])
            ->add('access', BadgeChoiceType::class,[
                'choices'   => [
                    'interlets'     => 'interlets', 
                    'users'         => 'users', 
                    'admin'         => 'admin',
                ],
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}
