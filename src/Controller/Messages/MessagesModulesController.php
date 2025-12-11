<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesModulesCommand;
use App\Form\Type\Messages\MessagesModulesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesModulesController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/messages/modules',
    name: 'messages_modules',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'messages',
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
      config_id: 'messages.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
    }

    $command = new MessagesModulesCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: MessagesModulesType::class,
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
            'key' => 'messages_modules.flash.change',
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

      return $this->redirectToRoute('messages_modules', $pp->ary());
    }

    return $this->render('messages/messages_modules.html.twig', [
      'form'          => $form->createView(),
    ]);
  }
}
