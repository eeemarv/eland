<?php declare(strict_types=1);

namespace App\Controller\UsersConfig;

use App\Command\UsersConfig\UsersConfigLeavingCommand;
use App\Form\Type\UsersConfig\UsersConfigLeavingType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersConfigLeavingController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/config/leaving',
    name: 'users_config_leaving',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
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
      config_id: 'users.leaving.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Leaving users not enabled.');
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
      $command = $form->getData();
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

    return $this->render('users_config/users_config_leaving.html.twig', [
      'form'=>  $form->createView(),
    ]);
  }
}
