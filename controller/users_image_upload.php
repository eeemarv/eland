<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class users_image_upload
{
    public function users_image_upload(Request $request, app $app):Response
    {
        if ($app['s_id'] < 1)
        {
            throw new AccessDeniedHttpException('Je hebt onvoldoende rechten voor deze actie.');
        }

        return $this->users_image_upload_admin($request, $app, $app['s_id']);
    }

    public function users_image_upload_admin(Request $request, app $app, int $id):Response
    {
        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        $filename = $app['image_upload']->gen_filename_for_user_image($id, $app['pp_schema']);
        $app['image_upload']->upload($uploaded_file, $filename, $app['pp_schema']);

        $app['db']->update($app['pp_schema'] . '.users', [
            '"PictureFile"'	=> $filename
        ],['id' => $id]);

        $app['monolog']->info('User image ' . $filename .
            ' uploaded. User: ' . $id,
            ['schema' => $app['pp_schema']]);

        $app['user_cache']->clear($id, $app['pp_schema']);

        return $app->json([$filename]);
    }
}
