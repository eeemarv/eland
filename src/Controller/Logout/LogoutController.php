<?php declare(strict_types=1);

namespace App\Controller\Logout;

use App\Repository\LogoutRepository;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class LogoutController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/logout',
    name: 'logout',
    methods: ['GET'],
    priority: 30,
    requirements: [
      'schema'        => '%assert.system%',
      'role_short'    => '%assert.role_short.guest%',
    ],
  )]

  public function __invoke(
    Request $request,
    RequestStack $request_stack,
    LogoutRepository $logout_repository,
    LoggerInterface $logger,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    $session = $request_stack->getSession();

    foreach($su->logins() as $schema => $user_id)
    {
      if ($user_id === 'master')
      {
          continue;
      }

      $logout_repository->insert(
        user_id: $user_id,
        agent: $request->server->get('HTTP_USER_AGENT'),
        ip: $request->getClientIp(),
        schema: $pp->schema_o(),
      );
    }

    $session->invalidate();

    $logger->info('user logged out', [
      'schema' => $pp->schema(),
    ]);

    $this->addFlash(
      type: 'success',
      message: [
        'key' => 'logout.flash.success',
      ],
    );

    if (!$pp->org_schema())
    {
      return $this->redirectToRoute(
        route: 'login',
        parameters: [
          'schema' => $pp->schema(),
        ],
      );
    }

    return $this->redirectToRoute(
      route: 'login',
      parameters: [
        'schema' => $pp->org_schema(),
      ],
    );
  }
}
