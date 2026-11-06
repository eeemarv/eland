<?php declare(strict_types=1);

namespace App\Form\Type\Transactions;

use App\Command\Transactions\TransactionsEditServiceStuffCommand;
use App\Form\Type\Field\AutocompleteAccountType;
use App\Form\Type\Field\BtnChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionsEditServiceStuffType extends AbstractType
{
  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $builder->add('from_account_id', AutocompleteAccountType::class, [
      'account_group' => 'users',
      'disabled'  => true,
    ]);

    $builder->add('to_account_id', AutocompleteAccountType::class, [
      'account_group' => 'users',
      'disabled'  => true,
    ]);

    $builder->add('amount', IntegerType::class, [
      'disabled'  => true,
    ]);

    $builder->add('description', TextType::class, [
      'disabled'  => true,
    ]);

    $builder->add('service_stuff', BtnChoiceType::class, [
      'choices' => [
        'service' => 'service',
        'stuff' => 'stuff',
        'null_service_stuff' => 'null_service_stuff',
      ],
    ]);

    $builder->add('verify', CheckboxType::class);
    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', TransactionsEditServiceStuffCommand::class);
  }
}
