<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class typeahead
{

    public function eland_intersystem_accounts(app $app):Response
    {
        return $app['legacy_route']->typeahead('eland_intersystem_accounts');
    }

    public function elas_intersystem_accounts(app $app):Response
    {
        return $app['legacy_route']->typeahead('elas_intersystem_accounts');
    }
}
