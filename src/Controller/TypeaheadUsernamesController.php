<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadUsernamesController extends AbstractController
{
    public function typeahead_usernames(app $app, Db $db):Response
    {
        $usernames = [];

        $st = $db->prepare('select name
            from ' . $app['pp_schema'] . '.users
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

        $crc = (string) crc32(json_encode($usernames));

        $typeahead_service->set_thumbprint('usernames', $app['pp_ary'], [], $crc);

        return $this->json($usernames);
    }
}
