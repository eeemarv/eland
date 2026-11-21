<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesCleanupCommand;
use App\Form\Type\Messages\MessagesCleanupType;
use App\Routing\AccessLevel;
use App\Routing\MessagesSubModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class MessagesCleanupController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: MessagesSubModule::Expire,
    path: '/messages/cleanup',
    name: 'messages_cleanup',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {

    $command = new MessagesCleanupCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: MessagesCleanupType::class,
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
            'key' => 'messages_cleanup.flash.change',
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

      return $this->redirectToRoute('messages_cleanup', $pp->ary());
    }

    return $this->render('messages/messages_cleanup.html.twig', [
      'form'      => $form->createView(),
    ]);
  }
}
