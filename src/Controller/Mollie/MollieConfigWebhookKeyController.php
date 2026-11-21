<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Command\Mollie\MollieConfigWebhookKeyCommand;
use App\Form\Type\Mollie\MollieConfigWebhookKeyType;
use App\Repository\SecretRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class MollieConfigWebhookKeyController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Mollie,
    path: '/mollie/config/webhook-key',
    name: 'mollie_config_webhook_key',
    methods: ['GET', 'POST'],
)]

  public function __invoke(
    Request $request,
    SecretRepository $secret_repository,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {

    $current = $secret_repository->get_current(
      name: 'mollie_webhook_key',
      schema: $pp->schema_o(),
    );

    $command = new MollieConfigWebhookKeyCommand();

    $form_options = [
      'log_comment_enabled' => true,
    ];

    $form = $this->createForm(
      type: MollieConfigWebhookKeyType::class,
      data: $command,
      options: $form_options,
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $webhook_key = $command->webhook_key;
      $log_comment = $form->get('log_comment')->getData();

      $changed = $secret_repository->add(
        name: 'mollie_webhook_key',
        value: $webhook_key,
        comment: $log_comment,
        created_by: $su->id(),
        schema: $pp->schema_o(),
      );

      if ($changed)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'mollie_config_webhook_key.flash.success',
            'params' => [
              'operation_type' => $current ? 'update' : 'add',
            ],
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

      return $this->redirectToRoute(
        route: 'mollie_config_webhook_key',
        parameters: $pp->ary(),
      );
    }

    return $this->render('mollie/mollie_config_webhook_key.html.twig', [
      'form'  => $form->createView(),
      'current' => $current,
    ]);
  }
}
