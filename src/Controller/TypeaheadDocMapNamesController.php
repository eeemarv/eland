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

        $st = $db->prepare('select distinct data->>\'map_name\' as map_name
            from xdb.aggs
            where agg_type = \'doc\'
                and agg_schema = ?
                and data->>\'map_name\' <> \'\'
            order by data->>\'map_name\' asc');

        $st->bindValue(1, $pp->schema());

        $st->execute();

        while ($row = $st->fetch())
        {
            $map_names[] = $row['map_name'];
        }

        $crc = (string) crc32(json_encode($map_names));

        $typeahead_service->set_thumbprint('doc_map_names', $pp->ary(), [], $crc);

        return $this->json($map_names);
    }
}
