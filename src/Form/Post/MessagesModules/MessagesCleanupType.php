<?php declare(strict_types=1);

namespace App\Form\Post\MessagesModules;

use App\Command\MessagesModules\MessagesCleanupCommand;
use App\Form\Input\NumberAddonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessagesCleanupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('cleanup_enabled', CheckboxType::class)
            ->add('cleanup_after_days', NumberAddonType::class)
            ->add('expires_at_days_default', NumberAddonType::class)
            ->add('expires_at_required', CheckboxType::class)
            ->add('expires_at_switch_enabled', CheckboxType::class)
            ->add('expire_notify', CheckboxType::class)
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => MessagesCleanupCommand::class,
        ]);
    }
}