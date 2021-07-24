<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route(
        '/{system}',
        name: 'home',
        methods: ['GET'],
        priority: 30,
        requirements: [
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'home',
        ],
    )]

    public function __invoke(
    ):Response
    {
        return $this->render('pages/home.html.twig', [
        ]);
    }
}
