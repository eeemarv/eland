<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsAutoMinLimitCommand;
use App\Form\Type\Transactions\TransactionsAutoMinLimitType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsAutoMinLimitController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/auto-min-limit',
    name: 'transactions_autominlimit',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'transactions',
      'sub_module'    => 'autominlimit',
    ],
  )]

  public function __invoke(
    Request $request,
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
      throw new NotFoundHttpException('Transactions module not enabled.');
    }

    if (!$config_service->get_bool(
      config_id: 'accounts.limits.auto_min.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw new NotFoundHttpException('Submodule auto min limit not enabled.');
    }

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
      $command = $form->getData();
      $changed = $config_service->store_command(
        command: $command,
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
