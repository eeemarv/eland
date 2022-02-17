<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

class TypeaheadPostcodesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/typeahead-postcodes/{thumbprint}',
        name: 'typeahead_postcodes',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
            'thumbprint'    => '%assert.thumbprint%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        string $thumbprint,
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $cached = $typeahead_service->get_cached_data($thumbprint, $pp, []);

        if ($cached !== false)
        {
            return new Response($cached, 200, ['Content-Type' => 'application/json']);
        }

        $postcodes = [];

        $stmt = $db->prepare('select distinct postcode
            from ' . $pp->schema() . '.users
            order by postcode asc');

        $res = $stmt->executeQuery();

        while ($row = $res->fetchAssociative())
        {
            if (empty($row['postcode']))
            {
                continue;
            }

            $postcodes[] = $row['postcode'];
        }

        $data = json_encode($postcodes);
        $typeahead_service->set_thumbprint($thumbprint, $data, $pp, []);
        return new Response($data, 200, ['Content-Type' => 'application/json']);
    }
}
