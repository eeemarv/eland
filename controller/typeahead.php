<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class typeahead
{
    public function accounts(app $app):Response
    {
        return $app['legacy_route']->typeahead('accounts');
    }

    public function account_codes(app $app):Response
    {
        return $app['legacy_route']->typeahead('account_codes');
    }

    public function doc_map_names(app $app):Response
    {
        return $app['legacy_route']->typeahead('doc_map_names');
    }

    public function eland_intersystem_accounts(app $app):Response
    {
        return $app['legacy_route']->typeahead('eland_intersystem_accounts');
    }

    public function elas_intersystem_accounts(app $app):Response
    {
        return $app['legacy_route']->typeahead('elas_intersystem_accounts');
    }

    public function log_types(app $app):Response
    {
        return $app['legacy_route']->typeahead('log_types');
    }
}
