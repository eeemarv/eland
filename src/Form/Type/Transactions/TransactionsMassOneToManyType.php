<?php declare(strict_types=1);

namespace App\Form\Type\Transactions;

use App\Command\Transactions\TransactionsMassOneToManyCommand;
use App\Form\Type\Field\AutocompleteAccountType;
use App\Form\Type\Field\BtnChoiceType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionsMassOneToManyType extends AbstractType
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
    $service_stuff_enabled = $this->config_service->get_bool(
      config_id: 'transactions.fields.service_stuff.enabled',
      schema: $this->pp->schema_o(),
    );

    $builder->add('from_account_id', AutocompleteAccountType::class, [
      'account_group' => 'users',
    ]);

    $builder->add('amounts', CollectionType::class, [
      'entry_type'  => IntegerType::class,
      'entry_options'  => [
        'required'  => false,
        'attr'  => [
          'min' => '1',
        ],
      ],
    ]);

    $builder->add('description', TextType::class);

    if ($service_stuff_enabled)
    {
      $builder->add('service_stuff', BtnChoiceType::class, [
        'choices' => [
          'service' => 'service',
          'stuff' => 'stuff',
        ],
      ]);
    }

    $builder->add('email_notify_en', CheckboxType::class);

    $builder->add('email_copy_en', CheckboxType::class);

    $builder->add('verify', CheckboxType::class);

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', TransactionsMassOneToManyCommand::class);
    $resolver->setDefault('accounts', []);
    $resolver->addAllowedTypes('accounts', 'array');
  }
}
