<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use cnst\status as cnst_status;

class users_del_admin
{
    public function users_del_admin(Request $request, app $app, int $id):Response
    {
        if ($app['s_id'] === $id)
        {
            throw new AccessDeniedHttpException(
                'Je kan je eigen account niet verwijderen.');
        }

        if ($app['db']->fetchColumn('select id
            from ' . $app['tschema'] . '.transactions
            where id_to = ? or id_from = ?', [$id, $id]))
        {
            throw new AccessDeniedHttpException('Een gebruiker met transacties
                kan niet worden verwijderd.');
        }

        $user = $app['user_cache']->get($id, $app['tschema']);

        if (!$user)
        {
            throw new NotFoundHttpException(
                sprintf('De gebruiker met id %1$d bestaat niet.', $id));
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
                $this->remove_user($app, $id);

                $status = cnst_status::THUMBPINT_ARY[$user['status']];

                if ($status === 'active')
                {
                    if ($user['status'] === 2)
                    {
                        $status = 'leaving';
                    }
                    else if (isset($user['adate'])
                        && $app['new_user_treshold'] < strtotime($user['adate']))
                    {
                        $status = 'new';
                    }
                }

                $app['link']->redirect($app['r_users'], $app['pp_ary'],
                    ['status' => $status]);
            }
        }

        $app['heading']->add('Gebruiker ');
        $app['heading']->add($app['account']->link($id, $app['pp_ary']));
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
        $out .= 'name="zend" class="btn btn-danger">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get();
    }

    private function remove_user(app $app, int $id):void
    {
        $user = $app['user_cache']->get($id, $app['tschema']);

        // remove messages

        $usr = $user['letscode'] . ' ' . $user['name'] . ' [id:' . $id . ']';
        $msgs = '';
        $st = $app['db']->prepare('select id, content,
                id_category, msg_type
            from ' . $app['tschema'] . '.messages
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
                ['schema' => $app['tschema']]);

            $app['db']->delete($app['tschema'] . '.messages',
                ['id_user' => $id]);
        }

        // remove orphaned images.

        $rs = $app['db']->prepare('select mp.id, mp."PictureFile"
            from ' . $app['tschema'] . '.msgpictures mp
                left join ' . $app['tschema'] . '.messages m on mp.msgid = m.id
            where m.id is null');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $app['db']->delete($app['tschema'] . '.msgpictures', ['id' => $row['id']]);
        }

        // update counts for each category

        $offer_count = $want_count = [];

        $rs = $app['db']->prepare('select m.id_category, count(m.*)
            from ' . $app['tschema'] . '.messages m, ' .
                $app['tschema'] . '.users u
            where  m.id_user = u.id
                and u.status IN (1, 2, 3)
                and msg_type = 1
            group by m.id_category');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $offer_count[$row['id_category']] = $row['count'];
        }

        $rs = $app['db']->prepare('select m.id_category, count(m.*)
            from ' . $app['tschema'] . '.messages m, ' .
                $app['tschema'] . '.users u
            where m.id_user = u.id
                and u.status IN (1, 2, 3)
                and msg_type = 0
            group by m.id_category');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $want_count[$row['id_category']] = $row['count'];
        }

        $all_cat = $app['db']->fetchAll('select id,
                stat_msgs_offers, stat_msgs_wanted
            from ' . $app['tschema'] . '.categories
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

            $app['db']->update($app['tschema'] . '.categories',
                $stats,
                ['id' => $cat_id]);
        }

        //delete contacts

        $app['db']->delete($app['tschema'] . '.contact',
            ['id_user' => $id]);

        //delete fullname access record.

        $app['xdb']->del('user_fullname_access', (string) $id, $app['tschema']);

        //finally, the user

        $app['db']->delete($app['tschema'] . '.users',
            ['id' => $id]);
        $app['predis']->expire($app['tschema'] . '_user_' . $id, 0);

        $app['alert']->success('De gebruiker is verwijderd.');

        $thumbprint_status = cnst_status::THUMBPINT_ARY[$user['status']];
        $app['thumbprint_accounts']->delete($thumbprint_status, $app['pp_ary'], $app['tschema']);

        $app['intersystems']->clear_cache($app['tschema']);
    }
}