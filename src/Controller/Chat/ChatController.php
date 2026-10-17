<?php declare(strict_types=1);

namespace App\Controller\Chat;

use App\Command\Config\ConfigAdminCommand;
use App\Form\Type\Config\ConfigAdminType;
use App\Repository\ChatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class ChatController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/chat',
    name: 'chat',
    methods: ['GET'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.guest%',
    ],
    defaults: [
      'module'        => 'chat',
    ],
  )]

  public function __invoke(
    Request $request,
    ChatRepository $chat_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'chat.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException(
        'Chat module not enabled.'
      );
    }

    $command = new ConfigAdminCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );



    return $this->render('chat/chat.html.twig', [
      'chat_rooms'  => [],
    ]);
  }
}
