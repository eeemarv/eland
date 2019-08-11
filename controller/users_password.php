<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class users_password
{
    public function form_self(Request $request, app $app):Response
    {
        return $this->status($request, $app, $status, $id);
    }

    public function form_admin(Request $request, app $app, int $id):Response
    {
        return $this->status($request, $app, $status, $id);

        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get();
    }
}
