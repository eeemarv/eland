<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadPostcodesController extends AbstractController
{
    public function typeahead_postcodes(app $app, Db $db):Response
    {
        $postcodes = [];

        $st = $app['db']->prepare('select distinct postcode
            from ' . $app['pp_schema'] . '.users
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

        $app['typeahead']->set_thumbprint('postcodes', $app['pp_ary'], [], $crc);

        return $app->json($postcodes);
    }
}
