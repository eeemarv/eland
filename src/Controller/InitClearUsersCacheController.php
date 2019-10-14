<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\PageParamsService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class InitClearUsersCacheController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        PageParamsService $pp,
        LinkRender $link_render,
        UserCacheService $user_cache_service
    ):Response
    {
        set_time_limit(300);

        error_log('*** clear users cache ***');

        $users = $db->fetchAll('select id
            from ' . $pp->schema() . '.users');

        foreach ($users as $u)
        {
            $user_cache_service->clear($u['id'], $pp->schema());
        }

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }
}
