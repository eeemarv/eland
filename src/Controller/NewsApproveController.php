<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class NewsApproveController extends AbstractController
{
    public function __invoke(
        int $id,
        Db $db,
        PageParamsService $pp,
        VarRouteService $vr,
        LinkRender $link_render,
        AlertService $alert_service
    ):Response
    {
        $data = [
            'approved'  => 't',
        ];

        if ($db->update($pp->schema() . '.news',
            $data, ['id' => $id]))
        {
            $alert_service->success('Nieuwsbericht goedgekeurd en gepubliceerd.');
        }
        else
        {
            $alert_service->error('Goedkeuren en publiceren nieuwsbericht mislukt.');
        }

        $link_render->redirect($vr->get('news'), $pp->ary(), ['id' => $id]);

        return new Response('');
    }
}
