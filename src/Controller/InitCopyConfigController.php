<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class InitCopyConfigController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        PageParamsService $pp,
        ConfigService $config_service,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        error_log('** Copy config **');

        $config_ary = $db->fetchAll('select value, setting
            from ' . $pp->schema() . '.config');

        foreach($config_ary as $rec)
        {
            if (!$config_service->exists($rec['setting'], $pp->schema()))
            {
                $config_service->set($rec['setting'], $pp->schema(), $rec['value']);
                error_log('Config value copied: ' . $rec['setting'] . ' ' . $rec['value']);
            }
        }

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }
}
