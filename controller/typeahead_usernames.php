<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class typeahead_usernames
{
    public function get(app $app):Response
    {
        $usernames = [];

        $st = $app['db']->prepare('select name
            from ' . $app['tschema'] . '.users
            order by name asc');

        $st->execute();

        while ($row = $st->fetch())
        {
            if (empty($row['name']))
            {
                continue;
            }

            $usernames[] = $row['name'];
        }

        $params = [
            'schema'	=> $app['tschema'],
        ];

        $crc = crc32(json_encode($usernames));

        $app['typeahead']->set_thumbprint('usernames', $params, $crc);

        return $app->json($usernames);
    }
}
