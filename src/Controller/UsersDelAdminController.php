<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use app\cnst\statuscnst;
use Doctrine\DBAL\Connection as Db;

class UsersDelAdminController extends AbstractController
{
    public function users_del_admin(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        if ($app['s_id'] === $id)
        {
            throw new AccessDeniedHttpException(
                'Je kan je eigen account niet verwijderen.');
        }

        if ($db->fetchColumn('select id
            from ' . $app['pp_schema'] . '.transactions
            where id_to = ? or id_from = ?', [$id, $id]))
        {
            throw new AccessDeniedHttpException('Een gebruiker met transacties
                kan niet worden verwijderd.');
        }

        $user = $app['user_cache']->get($id, $app['pp_schema']);

        if (!$user)
        {
            throw new NotFoundHttpException(
                'De gebruiker met id ' . $id . ' bestaat niet.');
        }

        if ($request->isMethod('POST'))
        {
            $errors = [];

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            $verify = $request->request->get('verify', '') ? true : false;

            if (!$verify)
            {
                $errors[] = 'Het controle nazichts-vakje
                    is niet aangevinkt.';
            }

            if (count($errors))
            {
                $app['alert']->error($errors);
            }
            else
            {
                $this->remove_user($app, $id, $db);

                $status = statuscnst::THUMBPINT_ARY[$user['status']];

                $app['link']->redirect($app['r_users'], $app['pp_ary'],
                    ['status' => $status]);
            }
        }

        $app['heading']->add('Gebruiker ');
        $app['heading']->add_raw($app['account']->link($id, $app['pp_ary']));
        $app['heading']->add(' verwijderen?');
        $app['heading']->fa('user');

        $out = '<p><font color="red">Alle Gegevens, Vraag en aanbod, ';
        $out .= 'Contacten en Afbeeldingen van ';
        $out .= $app['account']->link($id, $app['pp_ary']);
        $out .= ' worden verwijderd.</font></p>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post"">';

        $out .= '<div class="form-group">';
        $out .= '<label for="id_verify">';
        $out .= '<input type="checkbox" name="verify"';
        $out .= ' value="1" id="id_verify"> ';
        $out .= ' Ik ben wis en waarachtig zeker dat ';
        $out .= 'ik deze gebruiker wil verwijderen.';
        $out .= '</label>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel($app['r_users_show'],
            $app['pp_ary'], ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('users');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }

    private function remove_user(app $app, int $id, Db $db):void
    {
        $user = $app['user_cache']->get($id, $app['pp_schema']);

        // remove messages

        $usr = $user['letscode'] . ' ' . $user['name'] . ' [id:' . $id . ']';
        $msgs = '';
        $st = $db->prepare('select id, content,
                id_category, msg_type
            from ' . $app['pp_schema'] . '.messages
            where id_user = ?');

        $st->bindValue(1, $id);
        $st->execute();

        while ($row = $st->fetch())
        {
            $msgs .= $row['id'] . ': ' . $row['content'] . ', ';
        }
        $msgs = trim($msgs, '\n\r\t ,;:');

        if ($msgs)
        {
            $app['monolog']->info('Delete user ' . $usr .
                ', deleted Messages ' . $msgs,
                ['schema' => $app['pp_schema']]);

            $db->delete($app['pp_schema'] . '.messages',
                ['id_user' => $id]);
        }

        // remove orphaned images.

        $rs = $db->prepare('select mp.id, mp."PictureFile"
            from ' . $app['pp_schema'] . '.msgpictures mp
                left join ' . $app['pp_schema'] . '.messages m on mp.msgid = m.id
            where m.id is null');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $db->delete($app['pp_schema'] . '.msgpictures', ['id' => $row['id']]);
        }

        // update counts for each category

        $offer_count = $want_count = [];

        $rs = $db->prepare('select m.id_category, count(m.*)
            from ' . $app['pp_schema'] . '.messages m, ' .
                $app['pp_schema'] . '.users u
            where  m.id_user = u.id
                and u.status IN (1, 2, 3)
                and msg_type = 1
            group by m.id_category');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $offer_count[$row['id_category']] = $row['count'];
        }

        $rs = $db->prepare('select m.id_category, count(m.*)
            from ' . $app['pp_schema'] . '.messages m, ' .
                $app['pp_schema'] . '.users u
            where m.id_user = u.id
                and u.status IN (1, 2, 3)
                and msg_type = 0
            group by m.id_category');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $want_count[$row['id_category']] = $row['count'];
        }

        $all_cat = $db->fetchAll('select id,
                stat_msgs_offers, stat_msgs_wanted
            from ' . $app['pp_schema'] . '.categories
            where id_parent is not null');

        foreach ($all_cat as $val)
        {
            $offers = $val['stat_msgs_offers'];
            $wants = $val['stat_msgs_wanted'];
            $cat_id = $val['id'];

            $want_count[$cat_id] = $want_count[$cat_id] ?? 0;
            $offer_count[$cat_id] = $offer_count[$cat_id] ?? 0;

            if ($want_count[$cat_id] == $wants && $offer_count[$cat_id] == $offers)
            {
                continue;
            }

            $stats = [
                'stat_msgs_offers'	=> $offer_count[$cat_id] ?? 0,
                'stat_msgs_wanted'	=> $want_count[$cat_id] ?? 0,
            ];

            $db->update($app['pp_schema'] . '.categories',
                $stats,
                ['id' => $cat_id]);
        }

        //delete contacts

        $db->delete($app['pp_schema'] . '.contact',
            ['id_user' => $id]);

        //delete fullname access record.

        $app['xdb']->del('user_fullname_access', (string) $id, $app['pp_schema']);

        //finally, the user

        $db->delete($app['pp_schema'] . '.users',
            ['id' => $id]);
        $app['predis']->expire($app['pp_schema'] . '_user_' . $id, 0);

        $app['alert']->success('De gebruiker is verwijderd.');

        $thumbprint_status = statuscnst::THUMBPINT_ARY[$user['status']];
        $app['thumbprint_accounts']->delete($thumbprint_status, $app['pp_ary'], $app['pp_schema']);

        $app['intersystems']->clear_cache($app['pp_schema']);
    }
}