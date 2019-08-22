<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class plot_user_transactions
{
    public function plot_user_transactions(app $app, int $user_id, int $days):Response
    {
        $user = $app['user_cache']->get($user_id, $app['tschema']);

        if (!$user)
        {
            $app->abort(404, 'User not found');
        }

        $intersystem_names = $transactions = [];

        $st = $app['db']->prepare('select url, apimethod,
            localletscode as code, groupname as name
            from ' . $app['tschema'] . '.letsgroups');

        $st->execute();

        while ($row = $st->fetch())
        {
            if ($row['apimethod'] === 'internal')
            {
                continue;
            }

            $sys_schema = $app['systems']->get_schema_from_legacy_eland_origin($row['url']);
            $code = (string) $row['code'];

            if ($sys_schema)
            {
                $name = $app['config']->get('systemname', $sys_schema);
            }
            else
            {
                $name = $row['name'];
            }

            $intersystem_names[$code] = $name;
        }

        $balance = (int) $user['saldo'];

        $begin_date = date('Y-m-d H:i:s', time() - (86400 * $days));
        $end_date = date('Y-m-d H:i:s');

        $query = 'select t.id, t.amount, t.id_from, t.id_to,
                t.real_from, t.real_to, t.date, t.description,
                u.id as user_id, u.name, u.letscode as code,
                u.accountrole as role, u.status
            from ' . $app['tschema'] . '.transactions t, ' .
                $app['tschema'] . '.users u
            where (t.id_to = ? or t.id_from = ?)
                and (u.id = t.id_to or u.id = t.id_from)
                and u.id <> ?
                and t.date >= ?
                and t.date <= ?
            order by t.date desc';

        $fetched_transactions = $app['db']->fetchAll($query,
            [$user_id, $user_id, $user_id, $begin_date, $end_date]);

        $begin_date = strtotime($begin_date);
        $end_date = strtotime($end_date);

        foreach ($fetched_transactions as $t)
        {
            $date = strtotime($t['date']);
            $out = $t['id_from'] == $user_id ? true : false;
            $mul = $out ? 1 : -1;
            $amount = ((int) $t['amount']) * $mul;
            $balance += $amount;

            $name = strip_tags((string) $t['name']);
            $code = strip_tags((string) $t['code']);
            $real = $t['real_from'] ?? $t['real_to'] ?? null;

            unset($intersystem_name, $user_link, $user_label);

            if (isset($real))
            {
                $intersystem_name = $intersystem_names[$code] ?? $name;

                if ($app['s_admin'])
                {
                    $user_link = $app['link']->context_path($app['r_users_show'],
                        $app['pp_ary'], ['id' => $t['user_id']]);
                }

                if (strpos($real, '(') !== false
                    && strpos($real, ')') !== false)
                {
                    [$real_name, $real_code] = explode('(', $real);
                    $real_name = trim($real_name ?? '');
                    $real_code = trim($real_code ?? '', ' ()\t\n\r\0\x0B');
                    $user_label = $real_code . ' ' . $real_name;
                }
                else
                {
                    $user_label = $real;
                }
            }
            else
            {
                $user_label = $code . ' ' . $name;

                if ($app['s_admin']
                    || ($t['status'] === 1 || $t['status'] === 2))
                {
                    $user_link = $app['link']->context_path($app['r_users_show'],
                        $app['pp_ary'], ['id' => $t['user_id']]);
                }
            }

            $user_label = strip_tags($code) . ' ' . strip_tags($name);

            $transactions[] = [
                'amount' 	    => $amount,
                'date' 		    => $date,
                'link' 		    => $app['link']->context_path('transactions_show',
                    $app['pp_ary'], ['id' => $t['id']]),
                'user'          => [
                    'label'             => $user_label,
                    'link'              => $user_link,
                    'intersystem_name'  => $intersystem_name,
                ],
            ];
        }

        $transactions = array_reverse($transactions);

        return $app->json([
            'user_id' 		=> $user_id,
            'ticks' 		=> $days === 365 ? 12 : 4,
            'currency' 		=> $app['config']->get('currency', $app['tschema']),
            'transactions' 	=> $transactions,
            'beginBalance' 	=> $balance,
            'begin' 		=> $begin_date,
            'end' 			=> $end_date,
        ]);
    }
}
