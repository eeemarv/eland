<?php declare(strict_types=1);

namespace App\Form\Type\ContactTypes;

use App\Command\ContactTypes\ContactTypesCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactTypesDelType extends AbstractType
{
    public function __construct(
    )
    {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder
            ->add('name', TextType::class, [
                'disabled'  => true,
            ])
            ->add('abbrev', TextType::class, [
                'disabled'  => true,
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'data_class'    => ContactTypesCommand::class,
        ]);
    }
}