<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsMassManyToOneCommand;
use App\Form\Type\Transactions\TransactionsMassManyToOneType;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsMassManyToOneController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/transactions/many-to-one/{status}',
    name: 'transactions_mass_many_to_one',
    methods: ['GET', 'POST'],
    requirements: [
      'status' => '%assert.account_status%',
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.admin%',
    ],
    defaults: [
      'status' => 'active',
      'module' => 'transactions',
      'sub_module' => 'autominlimit',
    ],
  )]

  public function __invoke(
    Request $request,
    string $status,
    UserRepository $user_repository,
    TransactionRepository $transaction_repository,
    PageParamsService $pp,
    SessionUserService $su,
    ConfigService $config_service,
  ): Response {
    if (!$config_service->get_bool(
        config_id: 'transactions.enabled',
        schema: $pp->schema_o(),
      )
    )
    {
      throw $this->createNotFoundException(
        'Transactions module not enabled.'
      );
    }
    if (!$config_service->get_bool(
      config_id: 'transactions.mass.enabled',
      schema: $pp->schema_o(),
      )
    )
    {
      throw $this->createNotFoundException(
        'Mass transactions sub module not enabled.'
      );
    }

    $autominlimit_percentage = $config_service->get_int(
      config_id: 'accounts.limits.auto_min.percentage',
      schema: $pp->schema_o(),
    );
    $autominlimit_enabled = $config_service->get_bool(
      config_id: 'accounts.limits.auto_min.enabled',
      schema: $pp->schema_o(),
    );
    $limits_enabled = $config_service->get_bool(
      config_id: 'accounts.limits.enabled',
      schema: $pp->schema_o(),
    );
    $global_min_limit = $config_service->get_int(
      config_id: 'accounts.limits.global.min_limit',
      schema: $pp->schema_o(),
    );

    $users = $user_repository->get_all_by_status(
      status: $status,
      schema: $pp->schema_o(),
    );

    $command = new TransactionsMassManyToOneCommand();

    $form = $this->createForm(
      type: TransactionsMassManyToOneType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if (
      $form->isSubmitted()
      && $form->isValid()
    )
    {
      $autominlimit_perc = $autominlimit_percentage;
      $global_min = $global_min_limit;

      if (!$autominlimit_enabled)
      {
        $autominlimit_perc = null;
      }

      if (!$limits_enabled)
      {
        $autominlimit_perc = null;
        $global_min = null;
      }

      $transaction_repository->insert_mass_many_to_one(
        from_account_ids_amounts: $command->amounts,
        to_account_id: $command->to_account_id,
        description: $command->description,
        service_stuff: $command->service_stuff,
        created_by: $su->id() ?: null,
        autominlimit_percentage: $autominlimit_perc,
        global_min_limit: $global_min,
        schema: $pp->schema_o(),
      );


      if (true) {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'transactions_autominlimit.flash.change',
          ],
        );
      } else {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ],
        );
      }

      return $this->redirectToRoute('transactions_autominlimit', $pp->ary());
    }

    return $this->render('transactions/transactions_mass_many_to_one.html.twig', [
      'form' => $form->createView(),
      'users' => $users,
      'status'  => $status,
    ]);
  }
}
