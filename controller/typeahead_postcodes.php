<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class typeahead_postcodes
{
    public function get(app $app):Response
    {
        $postcodes = [];

        $st = $app['db']->prepare('select distinct postcode
            from ' . $app['tschema'] . '.users
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

        $params = [
            'schema'	=> $app['tschema'],
        ];

        $crc = crc32(json_encode($postcodes));

        $app['typeahead']->set_thumbprint('postcodes', $params, $crc);

        return $app->json($postcodes);
    }
}
