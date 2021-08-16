<?php declare(strict_types=1);

namespace App\Form\Post\Docs;

use App\Command\Docs\DocsCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocsDelType extends AbstractType
{
    public function __construct(
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file_location', TextType::class, [
                'disabled'  => true,
            ])
            ->add('original_filename', TextType::class, [
                'disabled'  => true,
            ])
            ->add('name', TextType::class, [
                'disabled'  => true,
            ])
            ->add('map_name', TextType::class, [
                'disabled'  => true,
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => DocsCommand::class,
        ]);
    }
}