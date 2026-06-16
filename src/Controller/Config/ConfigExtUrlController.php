<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigExtUrlCommand;
use App\Form\Type\Config\ConfigExtUrlType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigExtUrlController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/config/ext-url',
    name: 'config_ext_url',
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
    $command = new ConfigExtUrlCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(ConfigExtUrlType::class, $command);
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
            'key' => 'config_ext_url.flash.change',
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

      return $this->redirectToRoute('config_ext_url', $pp->ary());
    }

    return $this->render('config/config_ext_url.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
