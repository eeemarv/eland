<?php declare(strict_types=1);

namespace App\Controller\Notifications;

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
class NotificationsController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/notifications',
    name: 'notifications',
    methods: ['GET'],
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
    ChatRepository $chat_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {



    return $this->render('notifications/notifications_list.html.twig', [
      'notifications'  => [
        'djlkfdjs;alkf',
      ],
    ]);
  }
}
