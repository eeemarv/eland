<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class LogoUploadController extends AbstractController
{
    public function logo_upload(Request $request, app $app):Response
    {
        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        $filename = $app['image_upload']->upload($uploaded_file,
            'l', 0, 400, 100, $app['pp_schema']);

        $config_service->set('logo', $app['pp_schema'], $filename);

        $app['monolog']->info('Logo ' . $filename .
            ' uploaded.',
            ['schema' => $app['pp_schema']]);

        return $app->json([$filename]);
    }
}
