<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Command\Mollie\MolliePaymentsAddCommand;
use App\Form\Type\Filter\QTextSearchFilterType;
use App\Form\Type\Mollie\MolliePaymentsAddType;
use App\Render\LinkRender;
use App\Repository\MollieRepository;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MolliePaymentsAddController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/mollie/payments/add/{status}',
    name: 'mollie_payments_add',
    methods: ['GET', 'POST'],
    requirements: [
      'status'        => '%assert.account.status2%',
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'status'        => 'active',
      'module'        => 'users',
      'sub_module'    => 'mollie',
    ],
  )]

  public function __invoke(
    Request $request,
    string $status,
    MollieRepository $mollie_repository,
    UserRepository $user_repository,
    ConfigService $config_service,
    LinkRender $link_render,
    PageParamsService $pp,
    SessionUserService $su
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'mollie.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException(
        'Mollie submodule (users) not enabled.'
      );
    }

    $mollie_apikey = $config_service->get_str(
      config_id: 'mollie.apikey',
      schema: $pp->schema_o(),
    );

    if (!$mollie_apikey ||
      !(str_starts_with($mollie_apikey, 'test_')
      || str_starts_with($mollie_apikey, 'live_')))
    {
      if ($request->isMethod('GET'))
      {
        $this->addFlash('warning', 'Je kan geen betaalverzoeken aanmaken want
          er is geen Mollie apikey ingesteld in de ' .
          $link_render->link('mollie_config', $pp->ary(), [], 'configuratie', []));
      }
    }
    else if (!str_starts_with($mollie_apikey, 'live_'))
    {
      if ($request->isMethod('GET'))
      {
        $this->addFlash('warning', 'Er is geen <code>live_</code> Mollie apikey ingsteld in de ' .
          $link_render->link('mollie_config', $pp->ary(), [], 'configuratie', []) .
          '. Betalingen kunnen niet uitgevoerd worden!');
      }
    }

    $users = $user_repository->get_all_by_status_incl_last_mollie(
      status: $status,
      schema: $pp->schema_o(),
    );

    $filter_form = $this->createForm(QTextSearchFilterType::class);
    $filter_form->handleRequest($request);

    $command = new MolliePaymentsAddCommand();
    $command->amounts = array_fill_keys(array_keys($users), null);

    $form = $this->createForm(
      type: MolliePaymentsAddType::class,
      data: $command,
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $amounts = $command->amounts;
      $description = $command->description;

      $user_amount_ary = [];
      foreach ($amounts as $uid => $amount)
      {
        if (!isset($amount))
        {
          continue;
        }
        $amount_formatted = number_format($amount, 2, '.', '');
        $user_amount_ary[$uid] = $amount_formatted;
      }

      if (count($user_amount_ary))
      {
        $mollie_repository->insert_payment_requests(
          description: $description,
          created_by: $su->id() ?: null,
          user_amount_ary: $user_amount_ary,
          schema: $pp->schema_o(),
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'mollie_payments_add.flash.success',
            'params'  => [
              'count' => count($user_amount_ary),
              'description' => $description,
            ],
          ],
        );
        foreach ($user_amount_ary as $uid => $amount)
        {
          $this->addFlash(
            type: 'success',
            message: [
              'user'  => $users[$uid],
              'user_info' => strtr($amount, '.', ',') . ' EUR',
            ],
          );
        }
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

      return $this->redirectToRoute(
        route: 'mollie_payments',
        parameters: $pp->ary(),
      );
    }

    return $this->render('mollie/mollie_payments_add.html.twig', [
      'users'   => $users,
      'status'  => $status,
      'form'    => $form->createView(),
      'filter_form' => $filter_form->createView(),
    ]);
  }
}
