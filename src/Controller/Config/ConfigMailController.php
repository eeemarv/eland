<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigMailCommand;
use App\Form\Type\Config\ConfigMailType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigMailController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/config/mail',
    name: 'config_mail',
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
    $command = new ConfigMailCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: ConfigMailType::class,
      data: $command,
    );
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
            'key' => 'config_mail.flash.change',
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

      return $this->redirectToRoute('config_mail', $pp->ary());
    }

    return $this->render('config/config_mail.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
