<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class TypeaheadElasIntersystemAccountsController extends AbstractController
{
    public function typeahead_elas_intersystem_accounts(
        app $app,
        int $group_id,
        Db $db
    ):Response
    {
        $group = $db->fetchAssoc('select *
            from ' . $app['pp_schema'] . '.letsgroups
            where id = ?', [$group_id]);

        if (!$group || !$group['url'])
        {
            return $app->json([], 404);
        }

        if ($group['apimethod'] != 'elassoap')
        {
            return $app->json([], 404);
        }

        $domain = strtolower(parse_url($group['url'], PHP_URL_HOST));

        $accounts = $app['cache']->get($domain . '_typeahead_data');

        if (!$accounts)
        {
            $app['monolog']->debug('typeahead/elas_intersystem_accounts: empty for id ' .
                $group_id . ', url: ' . $group['url'],
                ['schema' => $app['pp_schema']]);

            return $app->json([], 404);
        }

        $params = [
            'group_id'  => $group_id,
        ];

        $crc = (string) crc32(json_encode($accounts));

        $app['typeahead']->set_thumbprint(
            'elas_intersystem_accounts', $app['pp_ary'], $params, $crc);

        return $app->json($accounts);
    }
}