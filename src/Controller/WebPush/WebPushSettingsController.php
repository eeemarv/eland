<?php declare(strict_types=1);

namespace App\Controller\WebPush;

use App\Command\UsersConfig\UsersConfigLeavingCommand;
use App\Form\Type\UsersConfig\UsersConfigLeavingType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class WebPushSettingsController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/webpush-settings',
    name: 'webpush_settings',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.user%',
    ],
    defaults: [
      'module'        => 'notifications',
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
      config_id: 'notifications.webpush.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Web Push not enabled.');
    }

    $command = new UsersConfigLeavingCommand();

    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: UsersConfigLeavingType::class,
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
            'key' => 'users_config_leaving.flash.change',
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

      return $this->redirectToRoute('users_config_leaving', $pp->ary());
    }

    return $this->render('webpush/webpush_settings.html.twig', [
      'form'=>  $form->createView(),
    ]);
  }
}
