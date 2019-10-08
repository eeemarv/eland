<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class logout
{
    public function logout(app $app):Response
    {
        foreach($app['s_logins'] as $sch => $uid)
        {
            $app['xdb']->set('logout', (string) $uid, ['time' => time()], $sch);
        }

        $app['session']->invalidate();

        $app['monolog']->info('user logged out',
            ['schema' => $app['pp_schema']]);

        $app['alert']->success('Je bent uitgelogd');

        $app['link']->redirect('login', ['system' => $app['pp_system']], []);

        return new Response('');
    }
}
