<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Controller\MessagesShowController;
use Doctrine\DBAL\Connection as Db;

class MessagesImagesUploadController extends AbstractController
{
    public function messages_add_images_upload(
        Request $request,
        app $app,
        string $form_token,
        Db $db
    ):Response
    {
        return $this->messages_edit_images_upload($request, $app, 0, $form_token, $db);
    }

    public function messages_edit_images_upload(
        Request $request,
        app $app,
        int $id,
        string $form_token,
        Db $db
    ):Response
    {
        if ($error = $form_token_service->get_ajax_error($form_token))
        {
            throw new BadRequestHttpException('Form token fout: ' . $error);
        }

        return $this->messages_images_upload($request, $app, $id, $db);
    }

    public function messages_images_upload(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $uploaded_files = $request->files->get('images', []);
        $insert_in_db = $request->attributes->get('form_token') ? false : true;

        $return_ary = [];

        if (!count($uploaded_files))
        {
            throw new BadRequestHttpException('Afbeeldingsbestand ontbreekt.');
        }

        if ($id)
        {
            $message = MessagesShowController::get_message($db, $id, $app['pp_schema']);

            $s_owner = !$app['pp_guest']
                && $app['s_system_self']
                && $app['s_id'] === $message['id_user']
                && $message['id_user'];

            if (!$s_owner && !$app['pp_admin'])
            {
                throw new AccessDeniedHttpException('Je hebt onvoldoende rechten
                    om een afbeelding op te laden voor
                    dit vraag of aanbod bericht.');
            }
        }
        else
        {
            $id = $db->fetchColumn('select max(id)
                from ' . $app['pp_schema'] . '.messages');
            $id++;
        }

        foreach ($uploaded_files as $uploaded_file)
        {
            $filename = $app['image_upload']->upload($uploaded_file,
                'm', $id, 400, 400, $app['pp_schema']);

            if ($insert_in_db)
            {
                $db->insert($app['pp_schema'] . '.msgpictures', [
                    'msgid'			=> $id,
                    '"PictureFile"'	=> $filename]);

                $logger->info('Message-Picture ' .
                    $filename . ' uploaded and inserted in db.',
                    ['schema' => $app['pp_schema']]);
            }
            else
            {
                $logger->info('Message-Picture ' .
                    $filename . ' uploaded, not (yet) inserted in db.',
                    ['schema' => $app['pp_schema']]);
            }

            $return_ary[] = $filename;
        }

        return $this->json($return_ary);
    }
}
