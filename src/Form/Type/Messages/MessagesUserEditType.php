<?php declare(strict_types=1);

namespace App\Form\Type\Messages;

use App\Command\Messages\MessagesUserCommand;
use App\Form\Type\Field\TypeaheadType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessagesUserEditType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ):void
    {
        $typeahead_add = [];
        $typeahead_add[] = ['accounts', ['status' => 'active']];

        $builder->add('user_id', TypeaheadType::class, [
            'add'       => $typeahead_add,
            'filter'    => 'accounts',
        ]);
        $builder->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver):void
    {
        $resolver->setDefault('data_class', MessagesUserCommand::class);
    }
}