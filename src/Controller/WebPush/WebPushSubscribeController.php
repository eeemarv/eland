<?php declare(strict_types=1);

namespace App\Controller\WebPush;

use App\Repository\WebPushRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class WebPushSubscribeController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/webpush-subscribe',
    name: 'webpush_subscribe',
    methods: ['POST'],
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
    WebPushRepository $webpush_repository,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'notifications.webpush.enabled',
      schema: $pp->schema_o(),
    ))
    {
      return $this->json([
        'error' => 'Web Push not enabled',
      ], 400);
    }

    $sub = json_decode($request->getContent(), true);

    if (!isset($sub['endpoint']))
    {
      return $this->json([
        'error' => 'Endpoint missing',
      ], 400);
    }

    if (!isset($sub['keys']))
    {
      return $this->json([
        'error' => 'Keys missing',
      ], 400);
    }

    if (!is_array($sub['keys']))
    {
      return $this->json([
        'error' => 'Keys should be array',
      ], 400);
    }

    if (!$su->id())
    {
      return $this->json([
        'error' => 'No id for user',
      ], 400);
    }

    $rst = $webpush_repository->insert_subscription(
      endpoint: $sub['endpoint'],
      keys: $sub['keys'],
      user_id: $su->id(),
      schema: $pp->schema_o(),
    );

    if ($rst)
    {
      return $this->json([
        'ok'  => true,
      ]);
    }

    return $this->json([
      'error' => 'error storing the web push subscription',
    ]);
  }
}
