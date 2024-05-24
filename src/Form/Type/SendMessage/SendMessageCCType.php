<?php declare(strict_types=1);

namespace App\Form\Type\SendMessage;

use App\Command\SendMessage\SendMessageCCCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class SendMessageCCType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder
            ->add('message', TextareaType::class, [
                'attr'      => [
                    'placeholder'   => $options['placeholder'],
                ],
            ])
            ->add('cc', CheckboxType::class)
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'placeholder'   => null,
            'data_class'    => SendMessageCCCommand::class,
        ]);
    }
}