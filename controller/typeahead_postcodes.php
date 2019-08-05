<?php declare(strict_types=1);

namespace controller;

use util\app;
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

        $crc = crc32(json_encode($postcodes));

        $app['typeahead']->set_thumbprint('postcodes', $app['pp_ary'], [], $crc);

        return $app->json($postcodes);
    }
}
