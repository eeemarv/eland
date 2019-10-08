<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UsersAddController extends AbstractController
{
    public function users_add(Request $request, app $app):Response
    {
        return users_edit_admin::form($request, $app, 0, false);
    }
}
