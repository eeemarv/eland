<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class transactions_sum
{
    public function in(app $app, int $days):Response
    {
        return $this->calc($app, $days, true);
    }

    public function out(app $app, int $days):Response
    {
        return $this->calc($app, $days, false);
    }

    private function calc(app $app, int $days, bool $in):Response
    {
        $ex_letscodes = $app['request']->query->get('ex', []);

        if (!is_array($ex_letscodes))
        {
            $app->abort(400, 'No array for codes (ex parameter)');
        }

        array_walk($ex_letscodes, function(&$value){ $value = trim($value); });

        $res = $in ? 'to' : 'from';
        $inp = $in ? 'from' : 'to';

        $end_unix = time();
        $begin_unix = $end_unix - ($days * 86400);
        $begin = gmdate('Y-m-d H:i:s', $begin_unix);

        $sql_where = [];
        $sql_params = [$begin];
        $sql_types = [\PDO::PARAM_STR];

        if (count($ex_letscodes))
        {
            $sql_where[] = 'u.letscode not in (?)';
            $sql_params[] = $ex_letscodes;
            $sql_types[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
        }

        $query = 'select sum(t.amount), t.id_' . $res . ' as uid
            from ' . $app['tschema'] . '.transactions t, ' .
                $app['tschema'] . '.users u
            where u.id = t.id_' . $inp . '
                and t.cdate > ?';

        if (count($sql_where))
        {
            $query .= ' and ' . implode(' and ', $sql_where);
        }

        $query .= ' group by t.id_' . $res;

        $stmt = $app['db']->executeQuery($query, $sql_params, $sql_types);

        $ary = [];

        while ($row = $stmt->fetch())
        {
            $ary[$row['uid']] = $row['sum'];
        }

        return $app->json($ary);
    }
}
