<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadDocMapNamesController extends AbstractController
{
    public function typeahead_doc_map_names(
        app $app,
        Db $db
    ):Response
    {
        $map_names = [];

        $st = $db->prepare('select distinct data->>\'map_name\' as map_name
            from xdb.aggs
            where agg_type = \'doc\'
                and agg_schema = ?
                and data->>\'map_name\' <> \'\'
            order by data->>\'map_name\' asc');

        $st->bindValue(1, $app['pp_schema']);

        $st->execute();

        while ($row = $st->fetch())
        {
            $map_names[] = $row['map_name'];
        }

        $crc = (string) crc32(json_encode($map_names));

        $typeahead_service->set_thumbprint('doc_map_names', $app['pp_ary'], [], $crc);

        return $app->json($map_names);
    }
}
