<?php declare(strict_types=1);

namespace App\Form\ColumnSelect;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;

use App\Form\ColumnSelect\QuantityColumnSelectType;
use App\Form\Input\NumberAddonType;
use App\Form\Typeahead\TypeaheadUserType;

class UserActivityColumnSelectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('period_days', NumberAddonType::class, [
                'required'  => false,
            ])
            ->add('exclude', CollectionType::class, [
                'required'      => false,
                'entry_type'    => TypeaheadUserType::class,
                'entry_options' => [
                    'source_route'  => 'user_typeahead',
                    'source_params' => [
                        'user_type'     => 'direct',
                    ],
                    'required'  => false,
                ],
                'allow_add'    => true,
            ])
            ->add('new_exclude', ButtonType::class)
            ->add('transaction', QuantityColumnSelectType::class)
            ->add('amount', QuantityColumnSelectType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
        ]);
    }
}