<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\GeocodeQueue;
use App\Render\LinkRender;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class InitQueueGeocodingController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $start,
        Db $db,
        GeocodeQueue $geocode_queue,
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        set_time_limit(300);

        error_log('*** Queue for Geocoding, start: ' . $start . ' ***');

        $rs = $db->prepare('select c.id_user, c.value
            from ' . $pp->schema() . '.contact c, ' .
                $pp->schema() . '.type_contact tc
            where c.id_type_contact = tc.id
                and tc.abbrev = \'adr\'
            order by c.id_user asc
            limit 50 offset ' . $start);

        $rs->execute();

        $more_geocoding = false;

        while ($row = $rs->fetch())
        {
            $geocode_queue->cond_queue([
                'adr'		=> $row['value'],
                'uid'		=> $row['id_user'],
                'schema'	=> $pp->schema(),
            ], 0);

            $more_geocoding = true;
        }

        if ($more_geocoding)
        {
            $start += 50;

            $link_render->redirect('init_queue_geocoding', $pp->ary(),
                ['start' => $start]);
        }

        $link_render->redirect('init', $pp->ary(),
            ['ok' => $request->attributes->get('_route')]);

        return new Response('');
    }
}
