<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\UsersEditAdminController;
use Doctrine\DBAL\Connection as Db;

class UsersEditController extends AbstractController
{
    public function users_edit(
        Request $request,
        app $app,
        Db $db
    ):Response
    {
        return UsersEditAdminController::form($request, $app, $app['s_id'], true, $db);
    }
}
