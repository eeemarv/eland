<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadDocMapNamesController extends AbstractController
{
    public function __invoke(
        Db $db,
        TypeaheadService $typeahead_service,
        PageParamsService $pp
    ):Response
    {
        $map_names = [];

        $st = $db->executeQuery('select name
            from ' . $pp->schema() . '.doc_maps
            order by name asc');

        while ($name = $st->fetchColumn())
        {
            $map_names[] = $name;
        }

        $crc = (string) crc32(json_encode($map_names));

        $typeahead_service->set_thumbprint('doc_map_names', $pp->ary(), [], $crc);

        return $this->json($map_names);
    }
}
