<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class typeahead_log_types
{
    public function typeahead_log_types(app $app):Response
    {
        $log_types = [];

        $st = $app['db']->prepare('select distinct type
            from xdb.logs
            where schema = ?
            order by type asc');

        $st->bindValue(1, $app['pp_schema']);

        $st->execute();

        while ($row = $st->fetch())
        {
            $log_types[] = $row['type'];
        }

        $crc = (string) crc32(json_encode($log_types));

        $app['typeahead']->set_thumbprint('log_types', $app['pp_ary'], [], $crc);

        return $app->json($log_types);
    }
}
