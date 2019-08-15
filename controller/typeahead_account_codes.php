<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class typeahead_account_codes
{
    public function typeahead_account_codes(app $app):Response
    {
        $account_codes = [];

        $st = $app['db']->prepare('select letscode
            from ' . $app['tschema'] . '.users
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
