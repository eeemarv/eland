<?php declare(strict_types=1);

namespace App\Controller\Chat;

use App\Command\Config\ConfigAdminCommand;
use App\Repository\ChatRepository;
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
class ChatController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Guest,
    module: AppModule::Chat,
    path: '/chat',
    name: 'chat',
    methods: ['GET'],
  )]

  public function __invoke(
    Request $request,
    ChatRepository $chat_repository,
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



    return $this->render('chat/chat.html.twig', [
      'chat_rooms'  => [],
    ]);
  }
}
