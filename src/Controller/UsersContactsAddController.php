<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\contacts_add;

class UsersContactsAddController extends AbstractController
{
    public function users_contacts_add(Request $request, app $app):Response
    {
        return contacts_add::form($request, $app, $app['s_id'], false);
    }

    public function users_contacts_add_admin(Request $request, app $app, int $user_id):Response
    {
        return contacts_add::form($request, $app, $user_id, false);
    }
}
