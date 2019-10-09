<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;

class UsersImageUploadController extends AbstractController
{
    public function users_image_upload(
        Request $request,
        app $app,
        Db $db
    ):Response
    {
        if ($app['s_id'] < 1)
        {
            throw new AccessDeniedHttpException('Je hebt onvoldoende rechten voor deze actie.');
        }

        return $this->users_image_upload_admin($request, $app, $app['s_id'], $db);
    }

    public function users_image_upload_admin(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $uploaded_file = $request->files->get('image');

        if (!$uploaded_file)
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        $filename = $app['image_upload']->upload($uploaded_file,
            'u', $id, 400, 400, $app['pp_schema']);

        $db->update($app['pp_schema'] . '.users', [
            '"PictureFile"'	=> $filename
        ],['id' => $id]);

        $logger->info('User image ' . $filename .
            ' uploaded. User: ' . $id,
            ['schema' => $app['pp_schema']]);

        $app['user_cache']->clear($id, $app['pp_schema']);

        return $this->json([$filename]);
    }
}
