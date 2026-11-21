<?php declare(strict_types=1);

namespace App\Controller\Accounts;

use App\Cnst\BulkCnst;
use App\Cnst\MessageTypeCnst;
use App\Command\Transactions\TransactionsAddCommand;
use App\Command\Transactions\TransactionsAddExternalCommand;
use App\DTO\Schema;
use App\Form\Type\Transactions\TransactionsAddExternalType;
use App\Form\Type\Transactions\TransactionsAddType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\AccountRepository;
use App\Routing\AccessLevel;
use App\Routing\AccountsSubModule;
use App\Routing\SchemaRoute;
use App\Service\AutoDeactivateService;
use App\Service\AutoMinLimitService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailTransactionService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use App\Service\TransactionService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class AccountsCodeAutoConfigController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AccountsSubModule::CodeAuto,
    path: '/accounts/code-auto-config',
    name: 'accounts_code_auto_config',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    AccountRepository $account_repository,
    LoggerInterface $logger,
    AccountRender $account_render,
    ItemAccessService $item_access_service,
    ConfigService $config_service,
    FormTokenService $form_token_service,
    IntersystemsService $intersystems_service,
    LinkRender $link_render,
    TransactionService $transaction_service,
    MailTransactionService $mail_transaction_service,
    SystemsService $systems_service,
    TypeaheadService $typeahead_service,
    AutoMinLimitService $autominlimit_service,
    AutoDeactivateService $auto_deactivate_service,
    UserCacheService $user_cache_service,
    PageParamsService $pp,
    SessionUserService $su
  ):Response
  {

    $mid = (int) $request->query->get('mid', 0);
    $tuid = (int) $request->query->get('tuid', 0);
    $tus = $request->query->get('tus', '');

    $currency = $config_service->get_str(
      config_id: 'transactions.currency.name',
      schema: $pp->schema_o(),
    );
    $currency_ratio = $config_service->get_int(
      config_id: 'transactions.currency.per_hour_ratio',
      schema: $pp->schema_o(),
    );
    $timebased_enabled = $config_service->get_bool(
      config_id: 'transactions.currency.timebased_en',
      schema: $pp->schema_o(),
    );
    $system_name = $config_service->get_str(
      config_id: 'system.name',
      schema: $pp->schema_o(),
    );
    $system_min_limit = $config_service->get_int(
      config_id: 'accounts.limits.global.min',
      schema: $pp->schema_o(),
    );
    $system_max_limit = $config_service->get_int(
      config_id: 'accounts.limits.global.max',
      schema: $pp->schema_o(),
    );
    $balance_equilibrium = $config_service->get_int(
      config_id: 'accounts.equilibrium',
      schema: $pp->schema_o(),
    ) ?? 0;
    $service_stuff_enabled = $config_service->get_bool(
      config_id: 'transactions.fields.service_stuff.enabled',
      schema: $pp->schema_o(),
    );
    $limits_enabled = $config_service->get_bool(
      config_id: 'accounts.limits.enabled',
      schema: $pp->schema_o(),
    );
    $new_users_days = $config_service->get_int(
      config_id: 'users.new.days',
      schema: $pp->schema_o(),
    ) ?? 0;
    $new_users_enabled = $config_service->get_bool(
      config_id: 'users.new.enabled',
      schema: $pp->schema_o(),
    );
    $leaving_users_enabled = $config_service->get_bool(
      config_id: 'users.leaving.enabled',
      schema: $pp->schema_o(),
    );

    $show_new_status = $new_users_enabled;

    if ($show_new_status)
    {
      $new_users_access = $config_service->get_str(
        config_id: 'users.new.access',
        schema: $pp->schema_o(),
      );
      $show_new_status = $item_access_service->is_visible($new_users_access);
    }

    $show_leaving_status = $leaving_users_enabled;

    if ($show_leaving_status)
    {
      $leaving_users_access = $config_service->get_str(
        config_id: 'users.leaving.access',
        schema: $pp->schema_o(),
      );
      $show_leaving_status = $item_access_service->is_visible($leaving_users_access);
    }

    /***
     *
     *
     */

    $command = new TransactionsAddExternalCommand();
    $form = $this->createForm(
      type: TransactionsAddExternalType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();


      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'transactions.flash.success',
        ],
      );

      return $this->redirectToRoute('transactions', $pp->ary());
    }

    return $this->render('transactions/transactions_add_external.html.twig', [
      'content'   => '',
      'form'  => $form->createView(),
    ]);
  }
}
