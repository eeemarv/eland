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
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
            }

            $db->delete($app['pp_schema'] . '.msgpictures', ['msgid' => $id]);

            if ($db->delete($app['pp_schema'] . '.messages', ['id' => $id]))
            {
                $column = 'stat_msgs_';
                $column .= $message['msg_type'] ? 'offers' : 'wanted';

                $db->executeUpdate('update ' . $app['pp_schema'] . '.categories
                    set ' . $column . ' = ' . $column . ' - 1
                    where id = ?', [$message['id_category']]);

                $app['alert']->success(ucfirst($message['label']['type_this']) . ' is verwijderd.');
                $app['link']->redirect($app['r_messages'], $app['pp_ary'], []);
            }

            $app['alert']->error(ucfirst($message['label']['type_this']) . ' is niet verwijderd.');
        }

        $app['heading']->add(ucfirst($message['label']['type_this']) . ' ');

        $app['heading']->add_raw($app['link']->link_no_attr('messages_show', $app['pp_ary'],
            ['id' => $id], $message['content']));

        $app['heading']->add(' verwijderen?');
        $app['heading']->fa('newspaper-o');

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

        if ($app['intersystem_en'] && $app['intersystems']->get_count($app['pp_schema']))
        {
            $out .= '<dt>Zichtbaarheid</dt>';
            $out .= '<dd>';
            $out .= $app['item_access']->get_label($message['local'] ? 'user' : 'guest');
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

        $out .= $app['link']->btn_cancel('messages_show', $app['pp_ary'], ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $app['form_token']->get_hidden_input();
        $out .= '</form></p>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('messages');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
