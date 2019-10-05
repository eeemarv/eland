<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use controller\messages_show;

class messages_images_upload
{
    public function messages_add_images_upload(
        Request $request,
        app $app,
        string $form_token
    ):Response
    {
        return $this->messages_edit_images_upload($request, $app, 0, $form_token);
    }

    public function messages_edit_images_upload(
        Request $request,
        app $app,
        int $id,
        string $form_token
    ):Response
    {
        if ($error = $app['form_token']->get_ajax_error($form_token))
        {
            throw new BadRequestHttpException('Form token fout: ' . $error);
        }

        return $this->messages_images_upload($request, $app, $id);
    }

    public function messages_images_upload(
        Request $request,
        app $app,
        int $id
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
            $message = messages_show::get_message($app['db'], $id, $app['pp_schema']);

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
            $id = $app['db']->fetchColumn('select max(id)
                from ' . $app['pp_schema'] . '.messages');
            $id++;
        }

        foreach ($uploaded_files as $uploaded_file)
        {
            $upload_data = $app['image_upload']->upload($uploaded_file,
                'm', $id, 400, 400, $app['pp_schema']);

            $filename = $upload_data['filename'];

            if ($insert_in_db)
            {
                $app['db']->insert($app['pp_schema'] . '.msgpictures', [
                    'msgid'			=> $id,
                    '"PictureFile"'	=> $filename]);

                $app['monolog']->info('Message-Picture ' .
                    $filename . ' uploaded and inserted in db.',
                    ['schema' => $app['pp_schema']]);
            }
            else
            {
                $app['monolog']->info('Message-Picture ' .
                    $filename . ' uploaded, not (yet) inserted in db.',
                    ['schema' => $app['pp_schema']]);
            }

            $return_ary[] = $upload_data;
        }

        return $app->json($return_ary);
    }
}
