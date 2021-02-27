<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Repository\AccountRepository;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\PageParamsService;
use App\Service\SystemsService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class PlotUserTransactionsController extends AbstractController
{
    public function __invoke(
        int $user_id,
        int $days,
        Db $db,
        AccountRepository $account_repository,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        LinkRender $link_render,
        PageParamsService $pp,
        VarRouteService $vr,
        SystemsService $systems_service
    ):Response
    {
        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
        $end_unix = time();
        $begin_unix = $end_unix - (86400 * $days);
        $end_datetime = \DateTimeImmutable::createFromFormat('U', (string) $end_unix);
        $begin_datetime = \DateTimeImmutable::createFromFormat('U', (string) $begin_unix);
        $begin_balance = $account_repository->get_balance_on_date($user_id, $begin_datetime, $pp->schema());

        $intersystem_names = [];
        $transactions = [];

        $st = $db->prepare('select url, apimethod,
            localletscode as code, groupname as name
            from ' . $pp->schema() . '.letsgroups');

        $st->execute();

        while ($row = $st->fetch())
        {
            if ($row['apimethod'] === 'internal')
            {
                continue;
            }

            $sys_schema = $systems_service->get_schema_from_legacy_eland_origin($row['url']);
            $code = (string) $row['code'];

            if ($sys_schema)
            {
                $name = $config_service->get_str('system.name', $sys_schema);
            }
            else
            {
                $name = $row['name'];
            }

            $intersystem_names[$code] = $name;
        }

        $query = 'select t.id, t.amount, t.id_from, t.id_to,
                t.real_from, t.real_to, t.created_at, t.description,
                u.id as user_id, u.name, u.code,
                u.role, u.status
            from ' . $pp->schema() . '.transactions t, ' .
                $pp->schema() . '.users u
            where (t.id_to = ? or t.id_from = ?)
                and (u.id = t.id_to or u.id = t.id_from)
                and u.id <> ?
                and t.created_at >= ?
                and t.created_at <= ?
            order by t.created_at asc';

        $fetched_transactions = $db->fetchAllAssociative($query,
            [$user_id, $user_id, $user_id, $begin_datetime, $end_datetime],
            [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT,
            Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE]
        );

        foreach ($fetched_transactions as $t)
        {
            $time = strtotime($t['created_at'] . ' UTC');
            $out = $t['id_from'] === $user_id;
            $mul = $out ? -1 : 1;
            $amount = ((int) $t['amount']) * $mul;

            $name = strip_tags((string) $t['name']);
            $code = strip_tags((string) $t['code']);
            $real = $t['real_from'] ?? $t['real_to'] ?? null;

            unset($intersystem_name, $user_link, $user_label);

            if (isset($real))
            {
                $intersystem_name = $intersystem_names[$code] ?? $name;

                if ($pp->is_admin())
                {
                    $user_link = $link_render->context_path('users_show',
                        $pp->ary(), ['id' => $t['user_id']]);
                }

                if (str_contains($real, '(')
                    && str_contains($real, ')'))
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

                if ($pp->is_admin()
                    || ($t['status'] === 1 || $t['status'] === 2))
                {
                    $user_link = $link_render->context_path('users_show',
                        $pp->ary(), ['id' => $t['user_id']]);
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
                'fdate'             => $date_format_service->get_from_unix($time, 'day', $pp->schema()),
                'link' 		        => $link_render->context_path('transactions_show',
                    $pp->ary(), ['id' => $t['id']]),
                'user'              => $tr_user,
            ];
        }

        return $this->json([
            'user_id' 		=> $user_id,
            'ticks' 		=> $days === 365 ? 12 : 4,
            'currency' 		=> $currency,
            'transactions' 	=> $transactions,
            'begin_balance' => $begin_balance,
            'begin_unix' 	=> $begin_unix,
            'end_unix' 		=> $end_unix,
        ]);
    }
}
