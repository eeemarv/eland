<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TransactionsSumInController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $days,
        Db $db,
        PageParamsService $pp
    ):Response
    {
        return self::calc(
            $request,
            $days,
            true,
            $db,
            $pp
        );
    }

    public static function calc(
        Request $request,
        int $days,
        bool $in,
        Db $db,
        PageParamsService $pp
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
            from ' . $pp->schema() . '.transactions t, ' .
                $pp->schema() . '.users u
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