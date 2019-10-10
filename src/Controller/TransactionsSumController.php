<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TransactionsSumController extends AbstractController
{
    public function transactions_sum_in(
        Request $request,
        int $days,
        Db $db
    ):Response
    {
        return $this->calc(
            $request,
            $days,
            true,
            $db
        );
    }

    public function transactions_sum_out(
        Request $request,
        int $days,
        Db $db
    ):Response
    {
        return $this->calc(
            $request,
            $days,
            false,
            $db
        );
    }

    private function calc(
        Request $request,
        int $days,
        bool $in,
        Db $db
    ):Response
    {
        $ex_letscodes = $request->query->get('ex', []);

        if (!is_array($ex_letscodes))
        {
            return $this->json([
                'error' => 'No array for codes (ex parameter)',
            ], 400);
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
            $sql_types[] = Db::PARAM_STR_ARRAY;
        }

        $query = 'select sum(t.amount), t.id_' . $res . ' as uid
            from ' . $app['pp_schema'] . '.transactions t, ' .
                $app['pp_schema'] . '.users u
            where u.id = t.id_' . $inp . '
                and t.cdate > ?';

        if (count($sql_where))
        {
            $query .= ' and ' . implode(' and ', $sql_where);
        }

        $query .= ' group by t.id_' . $res;

        $stmt = $db->executeQuery($query, $sql_params, $sql_types);

        $ary = [];

        while ($row = $stmt->fetch())
        {
            $ary[$row['uid']] = $row['sum'];
        }

        return $this->json($ary);
    }
}
