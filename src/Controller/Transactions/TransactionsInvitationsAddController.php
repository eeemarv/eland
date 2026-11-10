<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsInvitationsAddCommand;
use App\Form\Type\Transactions\TransactionsInvitationsAddType;
use App\Render\AccountRender;
use App\Repository\AccountRepository;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class TransactionsInvitationsAddController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/transactions/invitations/add',
    name: 'transactions_invitations_add',
    methods: ['GET', 'POST'],
    requirements: [
      'role_short'    => '%assert.role_short.user%',
    ],
    defaults: [
      'module'        => 'transactions',
    ],
  )]

  public function __invoke(
    Request $request,
    AccountRepository $account_repository,
    LoggerInterface $logger,
    AccountRender $account_render,
    ItemAccessService $item_access_service,
    ConfigService $config_service,
    SystemsService $systems_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'transactions.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException(
        'Transactions module not enabled.'
      );
    }

    if (!$config_service->get_bool(
      config_id: 'transactions.invitations.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException(
        'Transactions invitations submodule not enabled.'
      );
    }

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

    $command = new TransactionsInvitationsAddCommand();
    $form = $this->createForm(
      type: TransactionsInvitationsAddType::class,
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

    return $this->render('transactions/transactions_invitations_add.html.twig', [
      'content'   => '',
      'form'  => $form->createView(),
    ]);
  }
}
