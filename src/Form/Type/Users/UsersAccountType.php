<?php declare(strict_types=1);

namespace App\Form\Type\Users;

use App\Command\Users\UsersAccountCommand;
use App\Form\Type\Field\UniqueCheckType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersAccountType extends AbstractType
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
    $limits_enabled = $this->config_service->get_bool(
      config_id: 'accounts.limits.enabled',
      schema: $this->pp->schema_o(),
    );

    $builder->add('code', UniqueCheckType::class, [
      'route' => 'unique_check_account_codes',
    ]);

    if ($limits_enabled)
    {
      $builder->add('min_limit', IntegerType::class);
      $builder->add('max_limit', IntegerType::class);
    }

    $builder->add('submit', SubmitType::class);
  }

  public function configureOptions(OptionsResolver $resolver):void
  {
    $resolver->setDefault('data_class', UsersAccountCommand::class);
  }
}