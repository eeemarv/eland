<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class typeahead_doc_map_names
{
    public function get(app $app):Response
    {
        $map_names = [];

        $st = $app['db']->prepare('select distinct data->>\'map_name\' as map_name
            from xdb.aggs
            where agg_type = \'doc\'
                and agg_schema = ?
                and data->>\'map_name\' <> \'\'
            order by data->>\'map_name\' asc');

        $st->bindValue(1, $app['tschema']);

        $st->execute();

        while ($row = $st->fetch())
        {
            $map_names[] = $row['map_name'];
        }

        $crc = crc32(json_encode($map_names));

        $app['typeahead']->set_thumbprint('doc_map_names', $app['pp_ary'], [], $crc);

        return $app->json($map_names);
    }
}
