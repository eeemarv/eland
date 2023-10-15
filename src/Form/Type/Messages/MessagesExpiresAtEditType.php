<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use App\Command\Messages\MessagesExpiresAtCommand;
use App\Form\Type\Field\DatepickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessagesExpiresAtEditType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        foreach ($options['submit_buttons'] as $s)
        {
            $builder->add('submit_' . $s, SubmitType::class, [
                'validation_groups' => false,
            ]);
        }

        $builder->add('expires_at', DatepickerType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', MessagesExpiresAtCommand::class);
        $resolver->setDefault('submit_buttons', []);
        $resolver->setAllowedTypes('submit_buttons', 'array');
    }
}