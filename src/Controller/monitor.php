<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class monitor
{
    public function monitor(app $app):Response
    {
        $out = $app['monitor_process']->monitor();

        return $app->render('base/minimal.html.twig', [
            'content'   => $out,
        ]);
    }
}
