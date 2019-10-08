<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class index
{
    public function index(Request $request, app $app):Response
    {
        $app['menu']->set('index');

        $schemas = $app['systems']->get_schemas();

        asort($schemas);

        $response = $app->render('index/index.html.twig', [
            'schemas'       => $schemas,
        ]);

        $response->setEtag((string) crc32($response->getContent()));
        $response->setPublic();
        $response->isNotModified($request);

        return $response;
    }
}
