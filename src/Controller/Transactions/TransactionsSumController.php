<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Service\PageParamsService;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsSumController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions/sum-in/{days}',
        name: 'transactions_sum_in',
        methods: ['GET'],
        requirements: [
            'days'          => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'direction'     => 'in',
            'module'        => 'transactions',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/transactions/sum-out/{days}',
        name: 'transactions_sum_out',
        methods: ['GET'],
        requirements: [
            'days'          => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'direction'     => 'out',
            'module'        => 'transactions',
        ],
    )]

    public function __invoke(
        Request $request,
        int $days,
        string $direction,
        Db $db,
        PageParamsService $pp
    ):Response
    {
        $ex_codes = $request->query->all('ex');

        if (!is_array($ex_codes))
        {
            return [];
        }

        array_walk($ex_codes, function(&$value){ $value = trim($value); });

        $res = $direction === 'in' ? 'to' : 'from';
        $inp = $direction === 'in' ? 'from' : 'to';

        $end_unix = time();
        $begin_unix = $end_unix - ($days * 86400);
        $begin = gmdate('Y-m-d H:i:s', $begin_unix);

        $sql_where = [];
        $sql_params = [$begin];
        $sql_types = [\PDO::PARAM_STR];

        if (count($ex_codes))
        {
            $sql_where[] = 'u.code not in (?)';
            $sql_params[] = $ex_codes;
            $sql_types[] = ArrayParameterType::STRING;
        }

        $query = 'select sum(t.amount), t.id_' . $res . ' as uid
            from ' . $pp->schema() . '.transactions t, ' .
                $pp->schema() . '.users u
            where u.id = t.id_' . $inp . '
                and t.created_at > ?';

        if (count($sql_where))
        {
            $query .= ' and ' . implode(' and ', $sql_where);
        }

        $query .= ' group by t.id_' . $res;

        $res = $db->executeQuery($query, $sql_params, $sql_types);

        $ary = [];

        while ($row = $res->fetchAssociative())
        {
            $ary[$row['uid']] = $row['sum'];
        }

        return $this->json($ary);
    }
}
