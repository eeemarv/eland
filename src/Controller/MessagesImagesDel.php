<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Controller\MessagesShowController;
use Doctrine\DBAL\Connection as Db;

class MessagesImagesDel extends AbstractController
{
    public function messages_images_instant_del(
        app $app,
        int $id,
        string $img,
        string $ext,
        string $form_token,
        Db $db
    ):Response
    {
        $img .= '.' . $ext;

        if ($error = $app['form_token']->get_ajax_error($form_token))
        {
            throw new BadRequestHttpException('Form token fout: ' . $error);
        }

        $message = messages_show::get_message($db, $id, $app['pp_schema']);

        if (!$message)
        {
            throw new NotFoundHttpException('Bericht niet gevonden.');
        }

        $s_owner = $app['s_id'] === $message['id_user'];

        if (!($s_owner || $app['pp_admin']))
        {
            throw new AccessDeniedHttpException('Geen rechten om deze afbeelding te verwijderen');
        }

        $image = $db->fetchAssoc('select p."PictureFile"
            from ' . $app['pp_schema'] . '.msgpictures p
            where p.msgid = ?
                and p."PictureFile" = ?', [$id, $img]);

        if (!$image)
        {
            throw new NotFoundHttpException('Afbeelding niet gevonden');
        }

        $db->delete($app['pp_schema'] . '.msgpictures', ['"PictureFile"' => $img]);

        return $app->json(['success' => true]);
    }

    public function messages_images_del(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $errors = [];

        $message = messages_show::get_message($db, $id, $app['pp_schema']);

        $s_owner = $app['s_id'] && $app['s_id'] === $message['id_user'];

        if (!($s_owner || $app['pp_admin']))
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
                $db->delete($app['pp_schema'] . '.msgpictures', ['msgid' => $id]);

                $app['alert']->success('De afbeeldingen voor ' . $message['label']['type_this'] .
                    ' zijn verwijderd.');

                $app['link']->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
            }

            $app['alert']->error($errors);
        }

        $images = [];

        $st = $db->prepare('select "PictureFile"
            from ' . $app['pp_schema'] . '.msgpictures
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

        if ($app['pp_admin'])
        {
            $app['heading']->add_sub('Gebruiker: ');
            $app['heading']->add_sub_raw($app['account']->link($message['id_user'], $app['pp_ary']));
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
            $out .= '<span class="btn btn-danger btn-lg" data-img="';
            $out .= $img;
            $out .= '" ';
            $out .= 'data-url="';

            $form_token = $app['form_token']->get();

            [$img_base, $ext] = explode('.', $img);

            $out .= $app['link']->context_path('messages_images_instant_del', $app['pp_ary'], [
                'img'           => $img_base,
                'ext'           => $ext,
                'form_token'    => $form_token,
                'id'            => $id,
            ]);

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
        $out .= '<input type="submit" value="Alle verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= $app['form_token']->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('messages');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
