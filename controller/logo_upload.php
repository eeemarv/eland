<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class logo_upload
{
    public function logo_upload(Request $request, app $app):Response
    {
        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        $upload_data = $app['image_upload']->upload($uploaded_file,
            'l', 0, 400, 80, $app['pp_schema']);

        $filename = $upload_data['filename'];
        $width = $upload_data['width'];

        $app['config']->set('logo', $app['pp_schema'], $filename);
        $app['config']->set('logo_width', $app['pp_schema'], (string) $width);

        $app['monolog']->info('Logo ' . $filename .
            ' uploaded.',
            ['schema' => $app['pp_schema']]);

        return $app->json([$upload_data]);
    }
}
