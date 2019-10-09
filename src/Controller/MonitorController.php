<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class MonitorController extends AbstractController
{
    public function monitor(app $app):Response
    {
        $out = $app['monitor_process']->monitor();

        return $this->render('base/minimal.html.twig', [
            'content'   => $out,
        ]);
    }
}
