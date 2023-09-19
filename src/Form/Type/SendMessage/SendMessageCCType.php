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
    public function buildForm(FormBuilderInterface $builder, array $options):void
    {
        $builder->add('message', TextareaType::class, [
            'attr'      => [
                'placeholder'   => $options['placeholder'],
            ],
        ]);
        $builder->add('cc', CheckboxType::class);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('placeholder', null);
        $resolver->setAllowedTypes('placeholder', ['string', 'null']);
        $resolver->setDefault('data_class', SendMessageCCCommand::class);
    }
}