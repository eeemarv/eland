<?php declare(strict_types=1);

namespace App\Controller\Init;

use App\Queue\GeocodeQueue;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class InitQueueGeocodingController extends AbstractController
{
    #[Route(
        '/{system}/init/queue-geocoding/{start}',
        name: 'init_queue_geocoding',
        methods: ['GET'],
        requirements: [
            'start'         => '%assert.id%',
            'system'        => '%assert.system%',
        ],
        defaults: [
            'start'         => 0,
        ],
    )]

    public function __invoke(
        Request $request,
        int $start,
        Db $db,
        GeocodeQueue $geocode_queue,
        PageParamsService $pp,
        string $env_app_init_enabled
    ):Response
    {
        if (!$env_app_init_enabled)
        {
            throw new NotFoundHttpException('De init routes zijn niet ingeschakeld.');
        }

        set_time_limit(300);

        error_log('*** Queue for Geocoding, start: ' . $start . ' ***');

        $stmt = $db->prepare('select c.user_id, c.value
            from ' . $pp->schema() . '.contact c, ' .
                $pp->schema() . '.type_contact tc
            where c.id_type_contact = tc.id
                and tc.abbrev = \'adr\'
            order by c.user_id asc
            limit 50 offset ' . $start);

        $res = $stmt->executeQuery();

        $more_geocoding = false;

        while ($row = $res->fetchAssociative())
        {
            $geocode_queue->cond_queue([
                'adr'		=> $row['value'],
                'uid'		=> $row['user_id'],
                'schema'	=> $pp->schema(),
            ], 0);

            $more_geocoding = true;
        }

        if ($more_geocoding)
        {
            $start += 50;

            return $this->redirectToRoute('init_queue_geocoding', [
                ...$pp->ary(),
                'start' => $start,
            ]);
        }

        return $this->redirectToRoute('init', [
            ...$pp->ary(),
            'ok' => $request->attributes->get('_route'),
        ]);
    }
}
