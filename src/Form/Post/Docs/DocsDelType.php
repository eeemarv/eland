<?php declare(strict_types=1);

namespace App\Form\Type\Docs;

use App\Command\Docs\DocsCommand;
use App\Form\EventSubscriber\AccessFieldSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocsDelType extends AbstractType
{
    public function __construct(
        protected AccessFieldSubscriber $access_field_subscriber
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

            $this->access_field_subscriber->add('access',
                ['admin', 'user', 'guest'], ['disabled' => true]);
            $builder->addEventSubscriber($this->access_field_subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => DocsCommand::class,
        ]);
    }
}