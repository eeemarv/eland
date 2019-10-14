<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class InitEmptyCityDistanceController extends AbstractController
{
    public function __invoke(
        Request $request,
        PageParamsService $pp,
        Db $db,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        $db->executeQuery('delete from ' .
            $pp->schema() . '.city_distance');

        error_log('*** empty city_distance table (is not used anymore) *** ');

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }
}
