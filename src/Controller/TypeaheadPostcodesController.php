<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadPostcodesController extends AbstractController
{
    public function typeahead_postcodes(
        Db $db,
        TypeaheadService $typeahead_service
    ):Response
    {
        $postcodes = [];

        $st = $db->prepare('select distinct postcode
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

        $typeahead_service->set_thumbprint('postcodes', $app['pp_ary'], [], $crc);

        return $this->json($postcodes);
    }
}
