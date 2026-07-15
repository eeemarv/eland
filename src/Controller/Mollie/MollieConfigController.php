<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Command\Mollie\MollieConfigModeCommand;
use App\Form\Type\Mollie\MollieConfigModeType;
use App\Repository\SecretRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MollieConfigController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/mollie/config',
    name: 'mollie_config',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'mollie',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    SecretRepository $secret_repository,
    PageParamsService $pp,
    SessionUserService $su,
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

    $has = $secret_repository->have_values(
      name_ary: [
        'mollie_test_api_key',
        'mollie_live_api_key',
        'mollie_webhook_key',
      ],
      schema: $pp->schema_o(),
    );

    $has_test_api_key = $has['mollie_test_api_key'] ?? false;
    $has_live_api_key = $has['mollie_live_api_key'] ?? false;
    $has_webhook_key = $has['mollie_webhook_key'] ?? false;

    $form_options = [
      'has_test_api_key' => $has_test_api_key,
      'has_live_api_key' => $has_live_api_key,
      'has_webhook_key'  => $has_webhook_key,
      'log_comment_enabled' => true,
    ];

    $command = new MollieConfigModeCommand();

    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: MollieConfigModeType::class,
      data: $command,
      options: $form_options,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $log_comment = $form->get('log_comment')->getData();

      $changed = $config_service->store_command(
        command: $command,
        route: $pp->route(),
        user_id: $su->id() ?: null,
        schema: $pp->schema_o(),
        comment: $log_comment,
      );

      if ($changed)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'mollie_config.flash.change',
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
        route: 'mollie_config',
        parameters: $pp->ary(),
      );
    }

    return $this->render('mollie/mollie_config.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
