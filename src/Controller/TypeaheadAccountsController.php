<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadAccountsController extends AbstractController
{
    public function typeahead_accounts(
        app $app,
        string $status,
        Db $db
    ):Response
    {
        if ($app['pp_guest'] && $status !== 'active')
        {
            return $this->json(['error' => 'No access.'], 403);
        }

        if(!$app['pp_admin'] && !in_array($status, ['active', 'extern']))
        {
            return $this->json(['error' => 'No access.'], 403);
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
                return $this->json([
                    'error' => 'Non existing or allowed status code.',
                ], 404);
                break;
        }

        $fetched_users = $db->fetchAll(
            'select letscode as c,
                name as n,
                extract(epoch from adate) as a,
                status as s,
                postcode as p,
                saldo as b,
                minlimit as min,
                maxlimit as max
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

            if ($account['max'] == 999999999)
            {
                unset($account['max']);
            }

            if ($account['min'] == -999999999)
            {
                unset($account['min']);
            }

            $accounts[] = $account;
        }

        $params = [
            'status'	=> $status,
        ];

        $crc = (string) crc32(json_encode($accounts));

        $typeahead_service->set_thumbprint('accounts', $app['pp_ary'], $params, $crc);

        return $this->json($accounts);
    }
}
