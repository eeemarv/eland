<?php declare(strict_types=1);

namespace App\Controller\Index;

use App\Service\SystemsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IndexController extends AbstractController
{
    public function __invoke(
        Request $request,
        SystemsService $systems_service
    ):Response
    {
        $schemas = $systems_service->get_schemas();

        asort($schemas);

        $response = $this->render('index/index.html.twig', [
            'schemas'       => $schemas,
        ]);

        $response->setEtag(hash('crc32b', $response->getContent()));
        $response->setPublic();
        $response->isNotModified($request);

        return $response;
    }
}