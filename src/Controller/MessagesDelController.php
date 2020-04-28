<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Controller\MessagesShowController;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;

class MessagesDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        $message = MessagesShowController::get_message($db, $id, $pp->schema());

        if (!($su->is_owner($message['id_user']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException(
                'Je hebt onvoldoende rechten om dit bericht te verwijderen.');
        }

        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
            }

            if ($db->delete($pp->schema() . '.messages', ['id' => $id]))
            {
                $alert_service->success(ucfirst($message['label']['offer_want_this']) . ' is verwijderd.');
                $link_render->redirect($vr->get('messages'), $pp->ary(), []);
            }

            $alert_service->error(ucfirst($message['label']['offer_want_this']) . ' is niet verwijderd.');
        }

        $heading_render->add(ucfirst($message['label']['offer_want_this']) . ' ');

        $heading_render->add_raw($link_render->link_no_attr('messages_show', $pp->ary(),
            ['id' => $id], $message['subject']));

        $heading_render->add(' verwijderen?');
        $heading_render->fa('newspaper-o');

        $out = '<div class="panel panel-info printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl>';

        $out .= '<dt>Wie</dt>';
        $out .= '<dd>';
        $out .= $account_render->link($message['id_user'], $pp->ary());
        $out .= '</dd>';

        $out .= '<dt>Categorie</dt>';
        $out .= '<dd>';
        $out .= htmlspecialchars($message['catname'], ENT_QUOTES);
        $out .= '</dd>';

        $out .= '<dt>Geldig tot</dt>';
        $out .= '<dd>';
        $out .= $date_format_service->get($message['expires_at'], 'day', $pp->schema());
        $out .= '</dd>';

        if ($config_service->get_intersystem_en($pp->schema()) && $intersystems_service->get_count($pp->schema()))
        {
            $out .= '<dt>Zichtbaarheid</dt>';
            $out .= '<dd>';
            $out .= $item_access_service->get_label($message['access']);
            $out .= '</dd>';
        }

        $out .= '</dl>';

        $out .= '</div>';

        $out .= '<div class="panel-body">';
        $out .= nl2br($message['content']);
        $out .= '</div>';

        $out .= '<div class="panel-heading">';
        $out .= '<h3>';
        $out .= '<span class="danger">';
        $out .= 'Ben je zeker dat ' . $message['label']['offer_want_this'];
        $out .= ' moet verwijderd worden?</span>';

        $out .= '</h3>';

        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('messages_show', $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form></p>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('messages');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
