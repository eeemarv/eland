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
        '/{system}/{role_short}/typeahead-postcodes',
        name: 'typeahead_postcodes',
        methods: ['GET'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $postcodes = [];

        $st = $db->prepare('select distinct postcode
            from ' . $pp->schema() . '.users
            order by postcode asc');

        $st->execute();

        while ($row = $st->fetch())
        {
            if (empty($row['postcode']))
            {
                continue;
            }

            $postcodes[] = $row['postcode'];
        }

        $crc = (string) crc32(json_encode($postcodes));

        $typeahead_service->set_thumbprint('postcodes', $pp->ary(), [], $crc);

        return $this->json($postcodes);
    }
}
