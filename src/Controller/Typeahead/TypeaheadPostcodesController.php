<?php declare(strict_types=1);

namespace App\Controller\Typeahead;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
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
        $cached = $typeahead_service->get_cached_response_body($thumbprint, $pp, []);

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

        $response_body = json_encode($postcodes);
        $typeahead_service->store_response_body($response_body, $pp, []);
        return new Response($response_body, 200, ['Content-Type' => 'application/json']);
    }
}
