<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class users_add
{
    public function users_add(Request $request, app $app):Response
    {
        return users_edit_admin::form($request, $app, 0, false);
    }
}
