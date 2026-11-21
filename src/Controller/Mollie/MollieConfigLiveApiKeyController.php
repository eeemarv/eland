<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Command\Mollie\MollieConfigLiveApiKeyCommand;
use App\Form\Type\Mollie\MollieConfigLiveApiKeyType;
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
class MollieConfigLiveApiKeyController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::Mollie,
    path: '/mollie/config/live-api-key',
    name: 'mollie_config_live_api_key',
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
      name: 'mollie_live_api_key',
      schema: $pp->schema_o(),
    );

    $command = new MollieConfigLiveApiKeyCommand();

    $form_options = [
      'log_comment_enabled' => true,
    ];

    $form = $this->createForm(
      type: MollieConfigLiveApiKeyType::class,
      data: $command,
      options: $form_options,
    );

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $live_api_key = $command->live_api_key;
      $log_comment = $form->get('log_comment')->getData();

      $changed = $secret_repository->add(
        name: 'mollie_live_api_key',
        value: $live_api_key,
        comment: $log_comment,
        created_by: $su->id(),
        schema: $pp->schema_o(),
      );

      if ($changed)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'mollie_config_live_api_key.flash.success',
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
        route: 'mollie_config_live_api_key',
        parameters: $pp->ary(),
      );
    }

    return $this->render('mollie/mollie_config_live_api_key.html.twig', [
      'form'  => $form->createView(),
      'current' => $current,
    ]);
  }
}
