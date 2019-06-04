<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ajax
{
    public function elas_group_login(app $app):Response
    {
        return $app['legacy_route']->ajax('elas_group_login');
    }

    public function elas_soap_status(app $app):Response
    {
        return $app['legacy_route']->ajax('elas_soap_status');
    }

    public function plot_user_transactions(app $app):Response
    {
        return $app['legacy_route']->ajax('plot_user_transactions');
    }

    public function transactions_sum(app $app):Response
    {
        return $app['legacy_route']->ajax('transactions_sum');
    }

    public function weighted_balances(app $app):Response
    {
        return $app['legacy_route']->ajax('weighted_balances');
    }
}
