<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class typeahead_eland_intersystem_accounts
{
    public function typeahead_eland_intersystem_accounts(app $app, string $remote_schema):Response
    {
        $eland_intersystems = $app['intersystems']->get_eland($app['pp_schema']);

        if (!isset($eland_intersystems[$remote_schema]))
        {
            $app['monolog']->debug('typeahead/eland_intersystem_accounts: ' .
                $remote_schema . ' not valid',
                ['schema' => $app['pp_schema']]);

            return $app->json([], 404);
        }

        $fetched_users = $app['db']->fetchAll(
            'select letscode as c,
                name as n,
                extract(epoch from adate) as a,
                status as s,
                postcode as p,
                saldo as b,
                minlimit as min,
                maxlimit as max
            from ' . $remote_schema . '.users
            where status in (1, 2)
            order by id asc'
        );

        $accounts = [];

        foreach ($fetched_users as $account)
        {
            if ($account['s'] == 1)
            {
                unset($account['s']);
            }

            if ($account['max'] == 999999999)
            {
                unset($account['max']);
            }

            if ($account['min'] == -999999999)
            {
                unset($account['min']);
            }

            $accounts[] = $account;
        }

        $params = [
            'remote_schema' => $remote_schema,
        ];

        $crc = (string) crc32(json_encode($accounts));

        $app['typeahead']->set_thumbprint(
            'eland_intersystem_accounts', $app['pp_ary'], $params, $crc);

        return $app->json($accounts);
    }
}
