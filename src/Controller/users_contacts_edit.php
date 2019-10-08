<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\contacts_edit;

class users_contacts_edit
{
    public function users_contacts_edit(Request $request, app $app, int $contact_id):Response
    {
        return contacts_edit::form($request, $app, $app['s_id'], $contact_id, false);
    }

    public function users_contacts_edit_admin(Request $request, app $app, int $user_id, int $contact_id):Response
    {
        return contacts_edit::form($request, $app, $user_id, $contact_id, false);
    }
}
