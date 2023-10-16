<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use App\Command\Messages\MessagesSubjectCommand;
use App\Form\Type\Field\BtnChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessagesSubjectEditType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $builder->add('subject', TextType::class);
        $builder->add('offer_want', BtnChoiceType::class, [
            'choices'   => [
                'offer' => 'offer',
                'want'  => 'want',
            ],
        ]);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', MessagesSubjectCommand::class);
    }
}