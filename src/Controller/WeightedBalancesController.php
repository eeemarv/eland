<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\AccountRepository;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class WeightedBalancesController extends AbstractController
{
    public function __invoke(
        int $days,
        Db $db,
        AccountRepository $account_repository,
        PageParamsService $pp
    ):Response
    {
        $end_unix = time();
        $begin_unix = $end_unix - ($days * 86400);
        $begin_datetime = \DateTimeImmutable::createFromFormat('U', (string) $begin_unix);

        $balance_ary = $account_repository->get_balance_ary($pp->schema());

        $balance = [];

        $rs = $db->prepare('select id
            from ' . $pp->schema() . '.users');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $balance[$row['id']] = $balance_ary[$row['id']] ?? 0;
        }

        $next = array_map(function () use ($end_unix){ return $end_unix; }, $balance);
        $acc = array_map(function (){ return 0; }, $balance);

        $trans = $db->fetchAll('select id_to, id_from, amount, created_at
            from ' . $pp->schema() . '.transactions
            where created_at >= ?
            order by created_at desc',
            [$begin_datetime],
            [Types::DATE_IMMUTABLE]);

        foreach ($trans as $t)
        {
            $id_to = $t['id_to'];
            $id_from = $t['id_from'];
            $time = strtotime($t['created_at'] . ' UTC');
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

        return $this->json($weighted);
    }
}
