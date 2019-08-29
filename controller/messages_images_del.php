<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use controller\messages_show;

class messages_images_del
{
    public function messages_images_instant_del(
        app $app,
        int $id,
        string $img,
        string $form_token
    ):Response
    {
        $img .= '.jpg';

        if ($error = $app['form_token']->get_ajax_error($form_token))
        {
            throw new BadRequestHttpException('Form token fout: ' . $error);
        }

        $message = messages_show::get_message($app['db'], $id, $app['tschema']);

        if (!$message)
        {
            throw new NotFoundHttpException('Bericht niet gevonden.');
        }

        $s_owner = $app['s_id'] === $message['id_user'];

        if (!($s_owner || $app['s_admin']))
        {
            throw new AccessDeniedHttpException('Geen rechten om deze afbeelding te verwijderen');
        }

        $image = $app['db']->fetchAssoc('select p."PictureFile"
            from ' . $app['tschema'] . '.msgpictures p
            where p.msgid = ?
                and p."PictureFile" = ?', [$id, $img]);

        if (!$image)
        {
            throw new NotFoundHttpException('Afbeelding niet gevonden');
        }

        $app['db']->delete($app['tschema'] . '.msgpictures', ['"PictureFile"' => $img]);

        return $app->json(['success' => true]);
    }

    public function messages_images_del(Request $request, app $app, int $id):Response
    {
        $message = messages_show::get_message($app['db'], $id, $app['tschema']);

        $s_owner = $app['s_id'] && $app['s_id'] === $message['id_user'];

        if (!($s_owner || $app['s_admin']))
        {
            throw new AccessDeniedHttpException(
                'Je hebt onvoldoende rechten om deze afbeeldingen te verwijderen.');
        }

        if ($request->isMethod('POST'))
        {
            if ($error_form = $app['form_token']->get_error())
            {
                $errors[] = $error_form;
            }

            if (!count($errors))
            {
                $app['db']->delete($app['tschema'] . '.msgpictures', ['msgid' => $id]);

                $app['alert']->success('De afbeeldingen voor ' . $message['label']['type_this'] .
                    ' zijn verwijderd.');

                $app['link']->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
            }

            $app['alert']->error($errors);
        }

        $images = [];

        $st = $app['db']->prepare('select "PictureFile"
            from ' . $app['tschema'] . '.msgpictures
            where msgid = ?');
        $st->bindValue(1, $id);
        $st->execute();

        while ($row = $st->fetch())
        {
            $images[] = $row['PictureFile'];
        }

        if (!count($images))
        {
            $app['alert']->error(ucfirst($message['label']['type_the']) . ' heeft geen afbeeldingen.');
            $app['link']->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
        }

        $app['heading']->add('Afbeeldingen verwijderen voor ');
        $app['heading']->add($message['label']['type']);
        $app['heading']->add(' "');
        $app['heading']->add($message['content']);
        $app['heading']->add('"');

        $app['heading']->fa('newspaper-o');

        $app['assets']->add(['messages_images_del.js']);

        if ($app['s_admin'])
        {
            $app['heading']->add_sub('Gebruiker: ');
            $app['heading']->add_sub($app['account']->link($message['id_user'], $app['pp_ary']));
        }

        $out = '<div class="row">';

        foreach ($images as $img)
        {
            $out .= '<div class="col-xs-6 col-md-3">';
            $out .= '<div class="thumbnail">';
            $out .= '<img src="';
            $out .= $app['s3_url'] . $img;
            $out .= '" class="img-rounded">';

            $out .= '<div class="caption">';
            $out .= '<span class="btn btn-danger" data-img="';
            $out .= $img;
            $out .= '" ';
            $out .= 'data-url="';

            $form_token = $app['form_token']->get();

            $out .= $app['link']->context_path('messages_images_instant_del', $app['pp_ary'],
                ['img' => basename($img, '.jpg'), 'form_token' => $form_token, 'id' => $id]);

            $out .= '" role="button">';
            $out .= '<i class="fa fa-times"></i> ';
            $out .= 'Verwijderen</span>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '</div>';

        $out .= '<form method="post">';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<h3>Alle afbeeldingen verwijderen voor ';
        $out .= $message['label']['type_this'];
        $out .= ' "';
        $out .= $message['content'];
        $out .= '"?</h3>';

        $out .= $app['link']->btn_cancel('messages_show', $app['pp_ary'], ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Alle verwijderen" name="zend" class="btn btn-danger">';

        $out .= $app['form_token']->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('messages');

        return $app['tpl']->get();
    }
}
