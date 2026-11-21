<?php declare(strict_types=1);

namespace App\Controller\Notifications;

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
class NotificationsController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: AppModule::Notifications,
    path: '/notifications',
    name: 'notifications',
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



    return $this->render('notifications/notifications_list.html.twig', [
      'notifications'  => [
        'djlkfdj',
      ],
    ]);
  }
}
