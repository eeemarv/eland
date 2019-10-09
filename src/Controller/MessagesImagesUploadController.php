<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Controller\MessagesShowController;
use App\Service\FormTokenService;
use App\Service\ImageUploadService;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class MessagesImagesUploadController extends AbstractController
{
    public function messages_add_images_upload(
        Request $request,
        string $form_token,
        Db $db,
        FormTokenService $form_token_service,
        LoggerInterface $logger,
        ImageUploadService $image_upload_service
    ):Response
    {
        return $this->messages_edit_images_upload(
            $request,
            0,
            $form_token,
            $db,
            $form_token_service,
            $logger,
            $image_upload_service
        );
    }

    public function messages_edit_images_upload(
        Request $request,
        int $id,
        string $form_token,
        Db $db,
        FormTokenService $form_token_service,
        LoggerInterface $logger,
        ImageUploadService $image_upload_service
    ):Response
    {
        if ($error = $form_token_service->get_ajax_error($form_token))
        {
            throw new BadRequestHttpException('Form token fout: ' . $error);
        }

        return $this->messages_images_upload(
            $request,
            $id,
            $db,
            $logger,
            $image_upload_service
        );
    }

    public function messages_images_upload(
        Request $request,
        int $id,
        Db $db,
        LoggerInterface $logger,
        ImageUploadService $image_upload_service
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
            $filename = $image_upload_service->upload($uploaded_file,
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
