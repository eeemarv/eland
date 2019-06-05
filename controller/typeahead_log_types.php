<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class typeahead_log_types
{
    public function get(app $app):Response
    {
        $log_types = [];

        $st = $app['db']->prepare('select distinct type
            from xdb.logs
            where schema = ?
            order by type asc');

        $st->bindValue(1, $app['tschema']);

        $st->execute();

        while ($row = $st->fetch())
        {
            $log_types[] = $row['type'];
        }

        $crc = crc32(json_encode($log_types));

        $app['typeahead']->set_thumbprint('log_types', $app['pp_ary'], [], $crc);

        return $app->json($log_types);
    }
}
