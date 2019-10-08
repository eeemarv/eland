<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\users_edit_admin;

class users_edit
{
    public function users_edit(Request $request, app $app):Response
    {
        return users_edit_admin::form($request, $app, $app['s_id'], true);
    }
}
