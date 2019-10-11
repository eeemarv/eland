<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Controller\MessagesShowController;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use Doctrine\DBAL\Connection as Db;

class MessagesImagesDel extends AbstractController
{
    public function messages_images_instant_del(
        int $id,
        string $img,
        string $ext,
        string $form_token,
        Db $db,
        FormTokenService $form_token_service
    ):Response
    {
        $img .= '.' . $ext;

        if ($error = $form_token_service->get_ajax_error($form_token))
        {
            throw new BadRequestHttpException('Form token fout: ' . $error);
        }

        $message = MessagesShowController::get_message($db, $id, $pp->schema());

        if (!$message)
        {
            throw new NotFoundHttpException('Bericht niet gevonden.');
        }

        $s_owner = $su->id() === $message['id_user'];

        if (!($s_owner || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException('Geen rechten om deze afbeelding te verwijderen');
        }

        $image = $db->fetchAssoc('select p."PictureFile"
            from ' . $pp->schema() . '.msgpictures p
            where p.msgid = ?
                and p."PictureFile" = ?', [$id, $img]);

        if (!$image)
        {
            throw new NotFoundHttpException('Afbeelding niet gevonden');
        }

        $db->delete($pp->schema() . '.msgpictures', ['"PictureFile"' => $img]);

        return $this->json(['success' => true]);
    }

    public function messages_images_del(
        Request $request,
        int $id,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        MenuService $menu_service,
        string $env_s3_url
    ):Response
    {
        $errors = [];

        $message = messages_show::get_message($db, $id, $pp->schema());

        $s_owner = $su->id() && $su->id() === $message['id_user'];

        if (!($s_owner || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException(
                'Je hebt onvoldoende rechten om deze afbeeldingen te verwijderen.');
        }

        if ($request->isMethod('POST'))
        {
            if ($error_form = $form_token_service->get_error())
            {
                $errors[] = $error_form;
            }

            if (!count($errors))
            {
                $db->delete($pp->schema() . '.msgpictures', ['msgid' => $id]);

                $alert_service->success('De afbeeldingen voor ' . $message['label']['type_this'] .
                    ' zijn verwijderd.');

                $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);
            }

            $alert_service->error($errors);
        }

        $images = [];

        $st = $db->prepare('select "PictureFile"
            from ' . $pp->schema() . '.msgpictures
            where msgid = ?');
        $st->bindValue(1, $id);
        $st->execute();

        while ($row = $st->fetch())
        {
            $images[] = $row['PictureFile'];
        }

        if (!count($images))
        {
            $alert_service->error(ucfirst($message['label']['type_the']) . ' heeft geen afbeeldingen.');
            $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);
        }

        $heading_render->add('Afbeeldingen verwijderen voor ');
        $heading_render->add($message['label']['type']);
        $heading_render->add(' "');
        $heading_render->add($message['content']);
        $heading_render->add('"');

        $heading_render->fa('newspaper-o');

        $assets_service->add(['messages_images_del.js']);

        if ($pp->is_admin())
        {
            $heading_render->add_sub('Gebruiker: ');
            $heading_render->add_sub_raw($account_render->link($message['id_user'], $pp->ary()));
        }

        $out = '<div class="row">';

        foreach ($images as $img)
        {
            $out .= '<div class="col-xs-6 col-md-3">';
            $out .= '<div class="thumbnail">';
            $out .= '<img src="';
            $out .= $env_s3_url . $img;
            $out .= '" class="img-rounded">';

            $out .= '<div class="caption">';
            $out .= '<span class="btn btn-danger btn-lg" data-img="';
            $out .= $img;
            $out .= '" ';
            $out .= 'data-url="';

            $form_token = $form_token_service->get();

            [$img_base, $ext] = explode('.', $img);

            $out .= $link_render->context_path('messages_images_instant_del', $pp->ary(), [
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

        $out .= $link_render->btn_cancel('messages_show', $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Alle verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('messages');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
