<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsAddCommand;
use App\DTO\Schema;
use App\Form\Type\Transactions\TransactionsAddType;
use App\Repository\AccountRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\AutoDeactivateService;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class TransactionsAddController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: AppModule::Transactions,
    path: '/transactions/add',
    name: 'transactions_add',
    methods: ['GET', 'POST'],
    priority: 10,
  )]

  public function __invoke(
    Request $request,
    AccountRepository $account_repository,
    MessageRepository $message_repository,
    UserRepository $user_repository,
    LoggerInterface $logger,
    ItemAccessService $item_access_service,
    ConfigService $config_service,
    SystemsService $systems_service,
    AutoDeactivateService $auto_deactivate_service,
    UserCacheService $user_cache_service,
    PageParamsService $pp,
    SessionUserService $su
  ):Response
  {

    $command = new TransactionsAddCommand();

    $inter_ary = $systems_service->get_inter_ary($pp->schema());
    $to_schema = $pp->schema();
    $message_id = $request->query->get('mid');
    $to_user_id = $request->query->get('tuid');
    $to_user_schema = $request->query->get('tus');

    if (isset($to_user_schema) 
      && $to_user_schema !== $to_schema
    )
    {
      if (isset($inter_ary[$to_user_schema]))
      {
        $to_schema = $to_user_schema;
      }
      else
      {
        $to_schema = null;
      }
    }

    if (isset($message_id) && isset($to_schema))
    {
      $message = $message_repository->get_with_prev_next(
        id: (int) $message_id,
        schema: new Schema($to_schema),
      );

      if ($message && $message['is_published'])
      {
        $command->to_id = $message['user_id'];
        $subj = $message['subject'];
        if (strlen($subj) > 60)
        {
          $subj = substr($subj, 0 ,57);
          $subj .= '...';
        }
        $command->description = $subj;
      }
    }
    else if (isset($to_user_id) && isset($to_schema))
    {
      $to_user = $user_repository->get(
        id: (int) $to_user_id,
        schema: new Schema($to_schema),
      );

      if ($to_user && $to_user['is_active'])
      {
        $command->to_id = $to_user['id'];
      }
    }

    $command->from_id = $su->id() ?? null;





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

    $form = $this->createForm(
      type: TransactionsAddType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {



      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'transactions.flash.success',
        ],
      );

      return $this->redirectToRoute('transactions', $pp->ary());
    }

    return $this->render('transactions/transactions_add.html.twig', [
      'content'   => '',
      'form'  => $form->createView(),
    ]);
  }
}
