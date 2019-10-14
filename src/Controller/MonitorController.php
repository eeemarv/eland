<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\MonitorProcessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class MonitorController extends AbstractController
{
    public function __invoke(
        MonitorProcessService $monitor_process_service
    ):Response
    {
        $out = $monitor_process_service->monitor();

        return $this->render('base/minimal.html.twig', [
            'content'   => $out,
        ]);
    }
}
