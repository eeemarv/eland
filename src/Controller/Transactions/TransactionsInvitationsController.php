<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsInvitationsConfigCommand;
use App\Form\Type\Transactions\TransactionsInvitationsConfigType;
use App\Service\ConfigService;
use App\Service\FeaturesService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class TransactionsInvitationsController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/transactions/invitations',
    name: 'transactions_invitations',
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
    ConfigService $config_service,
    FeaturesService $features_service,
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

    /***
     *
     *
     */

    $command = new TransactionsInvitationsConfigCommand();

    $features_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: TransactionsInvitationsConfigType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $changed = $features_service->store_command(
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
            'key' => 'transactions_invitations_config.flash.change',
          ]);
      }
      else
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]);
      }

      return $this->redirectToRoute(
        route: 'transactions', 
        parameters: $pp->ary(),
      );
    }

    return $this->render('transactions/transactions_invitations_config.html.twig', [
      'content'   => '',
      'form'  => $form->createView(),
    ]);
  }
}
