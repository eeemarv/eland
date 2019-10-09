<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class PlotUserTransactionsController extends AbstractController
{
    public function plot_user_transactions(
        app $app,
        int $user_id,
        int $days,
        Db $db
    ):Response
    {
        $user = $app['user_cache']->get($user_id, $app['pp_schema']);

        if (!$user)
        {
            $app->abort(404, 'User not found');
        }

        $intersystem_names = $transactions = [];

        $st = $db->prepare('select url, apimethod,
            localletscode as code, groupname as name
            from ' . $app['pp_schema'] . '.letsgroups');

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
                $name = $config_service->get('systemname', $sys_schema);
            }
            else
            {
                $name = $row['name'];
            }

            $intersystem_names[$code] = $name;
        }

        $end_balance = (int) $user['saldo'];
        $balance = 0;

        $begin_unix = time() - (86400 * $days);
        $end_unix = time();

        $begin_date = gmdate('Y-m-d H:i:s', $begin_unix);
        $end_date = gmdate('Y-m-d H:i:s', $end_unix);

        $query = 'select t.id, t.amount, t.id_from, t.id_to,
                t.real_from, t.real_to, t.cdate, t.description,
                u.id as user_id, u.name, u.letscode as code,
                u.accountrole as role, u.status
            from ' . $app['pp_schema'] . '.transactions t, ' .
                $app['pp_schema'] . '.users u
            where (t.id_to = ? or t.id_from = ?)
                and (u.id = t.id_to or u.id = t.id_from)
                and u.id <> ?
                and t.cdate >= ?
                and t.cdate <= ?
            order by t.cdate asc';

        $fetched_transactions = $db->fetchAll($query,
            [$user_id, $user_id, $user_id, $begin_date, $end_date]);

        foreach ($fetched_transactions as $t)
        {
            $time = strtotime($t['cdate'] . ' UTC');
            $out = $t['id_from'] === $user_id;
            $mul = $out ? -1 : 1;
            $amount = ((int) $t['amount']) * $mul;
            $balance += $amount;

            $name = strip_tags((string) $t['name']);
            $code = strip_tags((string) $t['code']);
            $real = $t['real_from'] ?? $t['real_to'] ?? null;

            unset($intersystem_name, $user_link, $user_label);

            if (isset($real))
            {
                $intersystem_name = $intersystem_names[$code] ?? $name;

                if ($app['pp_admin'])
                {
                    $user_link = $link_render->context_path($app['r_users_show'],
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

                if ($app['pp_admin']
                    || ($t['status'] === 1 || $t['status'] === 2))
                {
                    $user_link = $link_render->context_path($app['r_users_show'],
                        $app['pp_ary'], ['id' => $t['user_id']]);
                }
            }

            $user_label = strip_tags($code) . ' ' . strip_tags($name);

            $tr_user = [
                'label'     => $user_label,
            ];

            if (isset($user_link))
            {
                $tr_user['link'] = $user_link;
            }

            if (isset($intersystem_name))
            {
                $tr_user['intersystem_name'] = $intersystem_name;
            }

            $transactions[] = [
                'amount' 	        => $amount,
                'time'              => $time,
                'fdate'             => $app['date_format']->get_from_unix($time, 'day', $app['pp_schema']),
                'link' 		        => $link_render->context_path('transactions_show',
                    $app['pp_ary'], ['id' => $t['id']]),
                'user'              => $tr_user,
            ];
        }

        $begin_balance = $end_balance - $balance;

        return $app->json([
            'user_id' 		=> $user_id,
            'ticks' 		=> $days === 365 ? 12 : 4,
            'currency' 		=> $config_service->get('currency', $app['pp_schema']),
            'transactions' 	=> $transactions,
            'begin_balance' => $begin_balance,
            'begin_unix' 	=> $begin_unix,
            'end_unix' 		=> $end_unix,
        ]);
    }
}
