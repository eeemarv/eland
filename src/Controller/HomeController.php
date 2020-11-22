<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends AbstractController
{
    public function __invoke(
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $menu_service->set('home');

        return $this->render('pages/home.html.twig', [
            'schema'    => $pp->schema(),
        ]);
    }
}
