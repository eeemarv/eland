<?php declare(strict_types=1);

namespace App\Controller\Auth;

use App\Render\LinkRender;
use App\Repository\LogoutRepository;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;

class LogoutController extends AbstractController
{
    public function __invoke(
        Request $request,
        LogoutRepository $logout_repository,
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

            $logout_repository->insert($user_id, $request, $schema);
        }

        $session->invalidate();

        $logger->info('user logged out',
            ['schema' => $pp->schema()]);

        $alert_service->success('logout.success');

        if ($pp->org_system() === '')
        {
            $link_render->redirect('login', ['system' => $pp->system()], []);
        }

        $link_render->redirect('login', ['system' => $pp->org_system()], []);

        return new Response('');
    }
}
