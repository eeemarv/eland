<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LogoutController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}logout',
        name: 'logout',
        methods: ['GET'],
        priority: 10,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        SessionInterface $session,
        LoggerInterface $logger,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        LinkRender $link_render
    ):Response
    {
        foreach($su->logins() as $schema => $user_id)
        {
            if ($user_id === 'master')
            {
                continue;
            }

            $db->insert($schema . '.logout', [
                'user_id'   => $user_id,
                'agent'     => $request->server->get('HTTP_USER_AGENT'),
                'ip'        => $request->getClientIp(),
            ]);
        }

        $session->invalidate();

        $logger->info('user logged out',
            ['schema' => $pp->schema()]);

        $alert_service->success('Je bent uitgelogd');

        if ($pp->org_system() === '')
        {
            $link_render->redirect('login', ['system' => $pp->system()], []);
        }

        $link_render->redirect('login', ['system' => $pp->org_system()], []);

        return new Response('');
    }
}
