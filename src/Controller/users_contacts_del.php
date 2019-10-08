<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\contacts_del;

class users_contacts_del
{
    public function users_contacts_del(Request $request, app $app, int $contact_id):Response
    {
        return contacts_del::form($request, $app, $app['s_id'], $contact_id, false);
    }

    public function users_contacts_del_admin(Request $request, app $app, int $user_id, int $contact_id):Response
    {
        return contacts_del::form($request, $app, $user_id, $contact_id, false);
    }
}
