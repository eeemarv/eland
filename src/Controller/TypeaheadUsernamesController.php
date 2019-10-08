<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class TypeaheadUsernamesController extends AbstractController
{
    public function typeahead_usernames(app $app):Response
    {
        $usernames = [];

        $st = $app['db']->prepare('select name
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

        $app['typeahead']->set_thumbprint('usernames', $app['pp_ary'], [], $crc);

        return $app->json($usernames);
    }
}
