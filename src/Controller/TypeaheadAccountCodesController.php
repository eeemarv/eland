<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadAccountCodesController extends AbstractController
{
    public function typeahead_account_codes(app $app, Db $db):Response
    {
        $account_codes = [];

        $st = $db->prepare('select letscode
            from ' . $app['pp_schema'] . '.users
            order by letscode asc');

        $st->execute();

        while ($row = $st->fetch())
        {
            if (empty($row['letscode']))
            {
                continue;
            }

            $account_codes[] = $row['letscode'];
        }

        $crc = (string) crc32(json_encode($account_codes));

        $app['typeahead']->set_thumbprint('account_codes', $app['pp_ary'], [], $crc);

        return $app->json($account_codes);
    }
}