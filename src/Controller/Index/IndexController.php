<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Service\SystemsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class IndexController extends AbstractController
{
    #[Route(
        '/',
        name: 'index',
        methods: ['GET'],
        priority: 40,
    )]

    public function __invoke(
        Request $request,
        SystemsService $systems_service
    ):Response
    {
        $schemas = array_keys($systems_service->get_schema_ary());

        asort($schemas);

        $response = $this->render('index/index.html.twig', [
            'schemas'       => $schemas,
        ]);

        $response->setEtag(hash('crc32b', $response->getContent()), true);
        $response->setPublic();
        $response->isNotModified($request);

        return $response;
    }
}
