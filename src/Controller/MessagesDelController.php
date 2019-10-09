<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Controller\MessagesShowController;
use Doctrine\DBAL\Connection as Db;

class MessagesDelController extends AbstractController
{
    public function messages_del(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $message = messages_show::get_message($db, $id, $app['pp_schema']);

        $s_owner = !$app['pp_guest']
            && $app['s_system_self']
            && $app['s_id'] === $message['id_user']
            && $message['id_user'];

        if (!($s_owner || $app['pp_admin']))
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

            $db->delete($app['pp_schema'] . '.msgpictures', ['msgid' => $id]);

            if ($db->delete($app['pp_schema'] . '.messages', ['id' => $id]))
            {
                $column = 'stat_msgs_';
                $column .= $message['msg_type'] ? 'offers' : 'wanted';

                $db->executeUpdate('update ' . $app['pp_schema'] . '.categories
                    set ' . $column . ' = ' . $column . ' - 1
                    where id = ?', [$message['id_category']]);

                $alert_service->success(ucfirst($message['label']['type_this']) . ' is verwijderd.');
                $link_render->redirect($app['r_messages'], $app['pp_ary'], []);
            }

            $alert_service->error(ucfirst($message['label']['type_this']) . ' is niet verwijderd.');
        }

        $heading_render->add(ucfirst($message['label']['type_this']) . ' ');

        $heading_render->add_raw($link_render->link_no_attr('messages_show', $app['pp_ary'],
            ['id' => $id], $message['content']));

        $heading_render->add(' verwijderen?');
        $heading_render->fa('newspaper-o');

        $out = '<div class="panel panel-info printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl>';

        $out .= '<dt>Wie</dt>';
        $out .= '<dd>';
        $out .= $app['account']->link($message['id_user'], $app['pp_ary']);
        $out .= '</dd>';

        $out .= '<dt>Categorie</dt>';
        $out .= '<dd>';
        $out .= htmlspecialchars($message['catname'], ENT_QUOTES);
        $out .= '</dd>';

        $out .= '<dt>Geldig tot</dt>';
        $out .= '<dd>';
        $out .= $message['validity'];
        $out .= '</dd>';

        if ($app['intersystem_en'] && $intersystems_service->get_count($app['pp_schema']))
        {
            $out .= '<dt>Zichtbaarheid</dt>';
            $out .= '<dd>';
            $out .= $item_access_service->get_label($message['local'] ? 'user' : 'guest');
            $out .= '</dd>';
        }

        $out .= '</dl>';

        $out .= '</div>';

        $out .= '<div class="panel-body">';
        $out .= htmlspecialchars($message['Description'], ENT_QUOTES);
        $out .= '</div>';

        $out .= '<div class="panel-heading">';
        $out .= '<h3>';
        $out .= '<span class="danger">';
        $out .= 'Ben je zeker dat ' . $message['label']['type_this'];
        $out .= ' moet verwijderd worden?</span>';

        $out .= '</h3>';

        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('messages_show', $app['pp_ary'], ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form></p>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('messages');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
