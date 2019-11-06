<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class typeahead_accounts
{
    public function typeahead_accounts(app $app, string $status):Response
    {
        if ($app['pp_guest'] && $status !== 'active')
        {
            return $app->json(['error' => 'No access.'], 403);
        }

        if(!$app['pp_admin'] && !in_array($status, ['active', 'extern']))
        {
            return $app->json(['error' => 'No access.'], 403);
        }

        switch($status)
        {
            case 'extern':
                $status_sql = '= 7';
                break;
            case 'inactive':
                $status_sql = '= 0';
                break;
            case 'ip':
                $status_sql = '= 5';
                break;
            case 'im':
                $status_sql = '= 6';
                break;
            case 'active':
                $status_sql = 'in (1, 2)';
                break;
            default:
                return $app->json([
                    'error' => 'Non existing or allowed status code.',
                ], 404);
                break;
        }

        $fetched_users = $app['db']->fetchAll(
            'select letscode as c,
                name as n,
                extract(epoch from adate) as a,
                status as s
            from ' . $app['pp_schema'] . '.users
            where status ' . $status_sql . '
            order by id asc'
        );

        $accounts = [];

        foreach ($fetched_users as $account)
        {
            if ($account['s'] == 1)
            {
                unset($account['s']);
            }

            $accounts[] = $account;
        }

        $params = [
            'status'	=> $status,
        ];

        $crc = (string) crc32(json_encode($accounts));

        $app['typeahead']->set_thumbprint('accounts', $app['pp_ary'], $params, $crc);

        return $app->json($accounts);
    }
}
