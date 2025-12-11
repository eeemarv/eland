<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigAdminCommand;
use App\Form\Type\Config\ConfigAdminType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigAdminController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/config/admin',
    name: 'config_admin',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'config',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    $command = new ConfigAdminCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(ConfigAdminType::class, $command);
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
            'key' => 'config_admin.flash.change',
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
      return $this->redirectToRoute('config_admin', $pp->ary());
    }

    return $this->render('config/config_admin.html.twig', [
      'form' => $form->createView(),
    ]);
  }
}
