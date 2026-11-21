<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsAutoMinLimitCommand;
use App\Form\Type\Transactions\TransactionsAutoMinLimitType;
use App\Routing\AccessLevel;
use App\Routing\LimitsSubModule;
use App\Routing\SchemaRoute;
use App\Routing\TransactionsSubModule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class TransactionsAutoMinLimitController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: LimitsSubModule::AutoMinLimit,
    path: '/auto-min-limit',
    name: 'transactions_autominlimit',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    PageParamsService $pp,
    SessionUserService $su,
    ConfigService $config_service,
  ):Response
  {
    $command = new TransactionsAutoMinLimitCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: TransactionsAutoMinLimitType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
        && $form->isValid())
    {
      $changed = $config_service->store_command(
        command: $command,
        route: $pp->route(),
        user_id: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      if ($changed)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'transactions_autominlimit.flash.change',
          ],
        );
      }
      else
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ],
        );
      }

      return $this->redirectToRoute('transactions_autominlimit', $pp->ary());
    }

    return $this->render('transactions/transactions_autominlimit.html.twig', [
      'form'      => $form->createView(),
    ]);
  }
}
