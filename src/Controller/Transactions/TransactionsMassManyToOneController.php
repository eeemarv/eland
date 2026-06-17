<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsAutoMinLimitCommand;
use App\Form\Type\Transactions\TransactionsAutoMinLimitType;
use App\Form\Type\Transactions\TransactionsMassManyToOneType;
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
      'status'        => '%assert.account_status%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'status'        => 'active',
      'module'        => 'transactions',
      'sub_module'    => 'autominlimit',
    ],
  )]

  public function __invoke(
    Request $request,
    string $status,
    UserRepository $user_repository,
    PageParamsService $pp,
    SessionUserService $su,
    ConfigService $config_service,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'transactions.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Transactions module not enabled.');
    }

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
