<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class messages
{
    public function match(Request $request, app $app):Response
    {
        error_log('LOGINS: ' . json_encode($app['s_logins']));

        return $app['legacy_route']->render('messages');
    }
}
