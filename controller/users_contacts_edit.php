<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\status as cnst_status;
use cnst\role as cnst_role;

class users_contacts_edit
{
    public function users_contacts_edit(Request $request, app $app, int $contact_id):Response
    {
        return $this->users_contacts_edit_admin($request, $app, $app['s_id'], $contact_id);
    }

    public function users_contacts_edit_admin(Request $request, app $app, int $user_id, int $contact_id):Response
    {


        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get();
    }
}
