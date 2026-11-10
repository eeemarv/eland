<?php declare(strict_types=1);

namespace App\Form\Type\Transactions;

use App\Service\ConfigService;
use App\Command\Transactions\TransactionsAddCommand;
use App\Form\Type\Field\AutocompleteAccountType;
use App\Form\Type\Field\BtnChoiceType;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class TransactionsAddType extends AbstractType
{
  public function __construct(
    private readonly ConfigService $config_service,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options,
  ):void
  {
    $service_stuff_enabled = $this->config_service->get_bool('transactions.fields.service_stuff.enabled', $this->pp->schema_o());

    $from_remote_account_options = [];
    $to_remote_account_options = [];

    if ($this->pp->is_admin())
    {

    }

    $from_id_options = [];
    $to_id_options = [];
    $to_remote_id_options = [];

    $builder->add('from_id', AutocompleteAccountType::class, $from_id_options);
    $builder->add('from_remote_account', TextType::class, $from_remote_account_options);
    $builder->add('to_id', AutocompleteAccountType::class, $to_id_options);
    $builder->add('to_remote_id', AutocompleteAccountType::class, $to_remote_id_options);
    $builder->add('to_remote_account', TextType::class, $to_remote_account_options);
    $builder->add('amount', IntegerType::class);
    $builder->add('remote_amount', IntegerType::class, [
      'required'  => false,
    ]);
    $builder->add('description', TextType::class);
    if ($service_stuff_enabled)
    {
      $builder->add('service_stuff', BtnChoiceType::class, [
        'choices'       => [
          'service'               => 'service',
          'stuff'                 => 'stuff',
        ],
      ]);
    }
    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', TransactionsAddCommand::class);
  }
}