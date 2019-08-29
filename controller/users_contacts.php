<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\status as cnst_status;
use cnst\role as cnst_role;

class users_contacts
{
    public function users_contacts(Request $request, app $app, int $id):Response
    {
        return $this->status($request, $app, $id);
    }

    public function users_contacts_admin(Request $request, app $app, string $status, int $id):Response
    {


        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get();
    }
}
