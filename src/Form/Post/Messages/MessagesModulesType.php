<?php declare(strict_types=1);

namespace App\Form\Post\Messages;

use App\Command\Messages\MessagesModulesCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessagesModulesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('service_stuff_enabled', CheckboxType::class)
            ->add('category_enabled', CheckboxType::class)
            ->add('expires_at_enabled', CheckboxType::class)
            ->add('units_enabled', CheckboxType::class)
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => MessagesModulesCommand::class,
        ]);
    }
}