<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class transactions_edit
{
    public function match(Request $request, app $app, int $id):Response
    {
        $intersystem_account_schemas = $app['intersystems']->get_eland_accounts_schemas($app['tschema']);

        $s_inter_schema_check = array_merge($app['intersystems']->get_eland($app['tschema']),
            [$app['s_schema'] => true]);

        $transaction = $app['db']->fetchAssoc('select t.*
            from ' . $app['tschema'] . '.transactions t
            where t.id = ?', [$id]);

        $inter_schema = false;

        if (isset($intersystem_account_schemas[$transaction['id_from']]))
        {
            $inter_schema = $intersystem_account_schemas[$transaction['id_from']];
        }
        else if (isset($intersystem_account_schemas[$transaction['id_to']]))
        {
            $inter_schema = $intersystem_account_schemas[$transaction['id_to']];
        }

        if ($inter_schema)
        {
            $inter_transaction = $app['db']->fetchAssoc('select t.*
                from ' . $inter_schema . '.transactions t
                where t.transid = ?', [$transaction['transid']]);
        }
        else
        {
            $inter_transaction = false;
        }

        $app['tpl']->add($out);
        $app['tpl']->menu('transactions');

        return $app['tpl']->get($request);
    }
}
