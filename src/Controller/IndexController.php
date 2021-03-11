<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\SystemsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    #[Route(
        '/',
        name: 'index',
        methods: ['GET'],
        priority: 20,
    )]

    public function __invoke(
        Request $request,
        SystemsService $systems_service
    ):Response
    {

        error_log('SCRIPT_NAME: ' . $request->getScriptName());
        error_log('HOST: ' . $request->getHost());

        $schemas = $systems_service->get_schemas();

        asort($schemas);

        $response = $this->render('index/index.html.twig', [
            'schemas'       => $schemas,
        ]);

        $response->setEtag((string) crc32($response->getContent()));
        $response->setPublic();
        $response->isNotModified($request);

        return $response;
    }
}
