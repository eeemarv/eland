<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class typeahead_elas_intersystem_accounts
{
    public function typeahead_elas_intersystem_accounts(app $app, int $group_id):Response
    {
        $group = $app['db']->fetchAssoc('select *
            from ' . $app['tschema'] . '.letsgroups
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

        $accounts = $app['cache']->get($domain . '_typeahead_data', false);

        if (!$accounts)
        {
            $app['monolog']->debug('typeahead/elas_intersystem_accounts: empty for id ' .
                $group_id . ', url: ' . $group['url'],
                ['schema' => $app['tschema']]);

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
