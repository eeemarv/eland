<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class weighted_balances
{
    public function get(app $app, int $days):Response
    {
        $end_unix = time();
        $begin_unix = $end_unix - ($days * 86400);
        $begin = gmdate('Y-m-d H:i:s', $begin_unix);

        $balance = [];

        $rs = $app['db']->prepare('select id, saldo
            from ' . $app['tschema'] . '.users');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $balance[$row['id']] = $row['saldo'];
        }

        $next = array_map(function () use ($end_unix){ return $end_unix; }, $balance);
        $acc = array_map(function (){ return 0; }, $balance);

        $trans = $app['db']->fetchAll('select id_to, id_from, amount, date
            from ' . $app['tschema'] . '.transactions
            where date >= ?
            order by date desc', [$begin]);

        foreach ($trans as $t)
        {
            $id_to = $t['id_to'];
            $id_from = $t['id_from'];
            $time = strtotime($t['date']);
            $period_to = $next[$id_to] - $time;
            $period_from = $next[$id_from] - $time;
            $acc[$id_to] += ($period_to) * $balance[$id_to];
            $next[$id_to] = $time;
            $balance[$id_to] -= $t['amount'];
            $acc[$id_from] += ($period_from) * $balance[$id_from];
            $next[$id_from] = $time;
            $balance[$id_from] += $t['amount'];
        }

        $weighted = [];

        foreach ($balance as $user_id => $b)
        {
            $acc[$user_id] += ($next[$user_id] - $begin_unix) * $b;
            $weighted[$user_id] = round($acc[$user_id] / ($days * 86400));
        }

        return $app->json($weighted);
    }
}