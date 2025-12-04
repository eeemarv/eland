<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersColsCommand;
use App\Form\Type\Field\DatepickerType;
use App\Form\Type\Field\TypeaheadType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersColsType extends AbstractType
{
  public function __construct(
    private readonly PageParamsService $pp,
    private readonly ConfigService $config_service,
  )
  {
  }

  public function buildForm(
    FormBuilderInterface $builder,
    array $options
  ):void
  {
    $full_name_enabled = $this->config_service->get_bool('users.fields.full_name.enabled', $this->pp->schema());
    $postcode_enabled = $this->config_service->get_bool('users.fields.postcode.enabled', $this->pp->schema());
    $birthdate_enabled = $this->config_service->get_bool('users.fields.birthdate.enabled', $this->pp->schema());
    $hobbies_enabled = $this->config_service->get_bool('users.fields.hobbies.enabled', $this->pp->schema());
    $comments_enabled = $this->config_service->get_bool('users.fields.comments.enabled', $this->pp->schema());
    $admin_comments_enabled = $this->config_service->get_bool('users.fields.admin_comments.enabled', $this->pp->schema());
    $periodic_mail_enabled = $this->config_service->get_bool('periodic_mail.enabled', $this->pp->schema());
    $mollie_enabled = $this->config_service->get_bool('mollie.enabled', $this->pp->schema());
    $messages_enabled = $this->config_service->get_bool('messages.enabled', $this->pp->schema());
    $transactions_enabled = $this->config_service->get_bool('transactions.enabled', $this->pp->schema());
    $limits_enabled = $this->config_service->get_bool('accounts.limits.enabled', $this->pp->schema());
    $is_admin = $this->pp->is_admin();

    $builder->setMethod('GET');

    if ($transactions_enabled)
    {
      $builder->add('code', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    $builder->add('name', CheckboxType::class, [
      'required'  => false,
    ]);
    if ($full_name_enabled)
    {
      $builder->add('full_name', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($postcode_enabled)
    {
      $builder->add('postcode', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($is_admin)
    {
      $builder->add('role', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($transactions_enabled)
    {
      $builder->add('balance', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('balance_on_date', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('balance_date', DatepickerType::class, [
        'required'  => false,
      ]);
      if ($limits_enabled)
      {
        $builder->add('min_limit', CheckboxType::class, [
          'required'  => false,
        ]);
        $builder->add('max_limit', CheckboxType::class, [
          'required'  => false,
        ]);
      }
    }
    if ($comments_enabled)
    {
      $builder->add('comments', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($hobbies_enabled)
    {
      $builder->add('hobbies', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($birthdate_enabled)
    {
      $builder->add('birthdate', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($is_admin && $admin_comments_enabled)
    {
      $builder->add('admin_comments', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($is_admin && $periodic_mail_enabled)
    {
      $builder->add('periodic_overview', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($is_admin)
    {
      $builder->add('created_at', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('last_edit_at', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('activated_at', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('last_login_at', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    $builder->add('contacts', UsersColsContactsType::class, [
      'contact_types' => $options['contact_types'],
    ]);
    $builder->add('distance', CheckboxType::class, [
      'required'  => false,
    ]);
    if ($is_admin && $mollie_enabled)
    {
      $builder->add('mollie', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($messages_enabled)
    {
      $builder->add('wants', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('offers', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('offers_and_wants', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    if ($transactions_enabled)
    {
      $typeahead_accounts_add = [];
      $typeahead_accounts_add[] = ['accounts', ['status' => 'active']];
      if ($this->pp->is_admin())
      {
        $typeahead_accounts_add[] = ['accounts', ['status' => 'extern']];
        $typeahead_accounts_add[] = ['accounts', ['status' => 'inactive']];
        $typeahead_accounts_add[] = ['accounts', ['status' => 'im']];
        $typeahead_accounts_add[] = ['accounts', ['status' => 'ip']];
      }

      $builder->add('transactions_days', IntegerType::class, [
        'required'  => true,
      ]);
      $builder->add('transactions_exclude_code', TypeaheadType::class, [
        'add'   => $typeahead_accounts_add,
        'filter'  => 'accounts',
        'required'  => false,
      ]);
      $builder->add('transactions_in', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('transactions_out', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('transactions_total', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('amount_in', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('amount_out', CheckboxType::class, [
        'required'  => false,
      ]);
      $builder->add('amount_total', CheckboxType::class, [
        'required'  => false,
      ]);
    }
    // value attr is needed to detect button with GET form
    $builder->add('submit', SubmitType::class, [
      'attr'  => [
        'value' => 'apply',
      ]
    ]);
    $builder->add('reset', SubmitType::class, [
      'attr'  => [
        'value' => 'reset',
      ]
    ]);
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setDefault('csrf_protection', false);
    $resolver->setDefault('form_token_enabled', false);
    $resolver->setDefault('data_class', UsersColsCommand::class);
    $resolver->setDefault('contact_types', []);
    $resolver->setAllowedTypes('contact_types', 'array');
  }

  public function getBlockPrefix():string
  {
    return 'cols';
  }
}
