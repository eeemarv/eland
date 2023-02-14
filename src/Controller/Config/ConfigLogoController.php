<?php declare(strict_types=1);

namespace App\Controller\Config;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigLogoController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/config/logo',
        name: 'config_logo',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'config',
        ],
    )]

    public function __invoke(
    ):Response
    {
        return $this->render('config/config_logo.html.twig', [
        ]);
    }
}
