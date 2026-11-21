<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigMailAddrCommand;
use App\Form\Type\Config\ConfigMailAddrType;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class ConfigMailAddrController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Config,
    path: '/config/mail-addr',
    name: 'config_mail_addr',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    $command = new ConfigMailAddrCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(ConfigMailAddrType::class, $command);
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
            'key' => 'config_mail_addr.flash.change',
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
        route: 'config_mail_addr', 
        parameters: $pp->ary(),
      );
    }

    return $this->render('config/config_mail_addr.html.twig', [
      'form'   => $form->createView(),
    ]);
  }
}
