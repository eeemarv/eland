<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use App\Command\Messages\MessagesCleanupCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessagesCleanupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('cleanup_enabled', CheckboxType::class);
        $builder->add('cleanup_after_days', IntegerType::class);
        $builder->add('expires_at_days_default', IntegerType::class);
        $builder->add('expires_at_required', CheckboxType::class);
        $builder->add('expires_at_switch_enabled', CheckboxType::class);
        $builder->add('expire_notify', CheckboxType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', MessagesCleanupCommand::class);
    }
}