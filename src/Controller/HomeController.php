<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        Request $request,
        SessionUserService $su
    ):Response
    {
        $response = $this->render('pages/home.html.twig');

        $logins = $su->logins();

        if (empty($logins)){
            $response->setEtag(hash('crc32b', $response->getContent()), true);
            $response->setPublic();
            $response->isNotModified($request);
        }

        return $response;
    }
}
