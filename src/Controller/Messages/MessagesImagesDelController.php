<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\Connection as Db;

class MessagesImagesDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        MessageRepository $message_repository,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service,
        string $env_s3_url
    ):Response
    {
        $errors = [];

        $message = $message_repository->get($id, $pp->schema());

        if (!($su->is_owner($message['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException( 'Access Denied for message with id ' . $id);
        }

        $images = array_values(json_decode($message['image_files'] ?? '[]', true));

        if (!count($images))
        {
            $alert_service->error(ucfirst($message['label']['offer_want_the']) . ' heeft geen afbeeldingen.');
            $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);
        }

        if ($request->isMethod('POST'))
        {
            if ($error_form = $form_token_service->get_error())
            {
                $errors[] = $error_form;
            }

            if (!count($errors))
            {
                $db->update($pp->schema() . '.messages', ['image_files' => '[]'], ['id' => $id]);

                $alert_service->success('De afbeeldingen voor ' . $message['label']['offer_want_this'] .
                    ' zijn verwijderd.');

                $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);
            }

            $alert_service->error($errors);
        }

        $heading_render->add('Afbeeldingen verwijderen voor ');
        $heading_render->add($message['label']['offer_want']);
        $heading_render->add(' "');
        $heading_render->add($message['subject']);
        $heading_render->add('"');

        $heading_render->fa('newspaper-o');

        $assets_service->add(['messages_images_del.js']);

        if ($pp->is_admin())
        {
            $heading_render->add_sub('Gebruiker: ');
            $heading_render->add_sub_raw($account_render->link($message['user_id'], $pp->ary()));
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

        $out .= '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out .= '<form method="post">';

        $out .= '<h3>Alle afbeeldingen verwijderen voor ';
        $out .= $message['label']['offer_want_this'];
        $out .= ' "';
        $out .= $message['subject'];
        $out .= '"?</h3>';

        $out .= $link_render->btn_cancel('messages_show', $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Alle verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('messages');

        return $this->render('messgaes/messages_images_del.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}