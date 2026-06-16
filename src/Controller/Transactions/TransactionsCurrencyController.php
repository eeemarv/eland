<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsCurrencyCommand;
use App\Form\Type\Transactions\TransactionsCurrencyType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsCurrencyController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/transactions/currency',
    name: 'transactions_currency',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'transactions',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'transactions.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Transactions module not enabled.');
    }

    $command = new TransactionsCurrencyCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: TransactionsCurrencyType::class,
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
            'key' => 'transactions_currency.flash.change',
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

      return $this->redirectToRoute('transactions_currency', $pp->ary());
    }

    return $this->render('transactions/transactions_currency.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
