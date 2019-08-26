<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Connection as db;
use render\link;
use cnst\access as cnst_access;

class messages_show
{
    public function messages_show(Request $request, app $app, int $id):Response
    {
        $message = self::get_message($app['db'], $id, $app['tschema']);

        if ($message['access'] === 'user' && $app['s_guest'])
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot dit bericht.');
        }

        $s_owner = !$app['s_guest']
            && $app['s_system_self']
            && $app['s_id'] === $message['id_user']
            && $message['id_user'];

        $ow_type = $message['is_offer'] ? 'aanbod' : 'vraag';
	    $ow_type_this = $message['is_offer'] ? 'dit aanbod' : 'deze vraag';

        $cc = $request->isMethod('POST') ? $cc : 1;

        $user = $app['user_cache']->get($message['id_user'], $app['tschema']);

        $mail_to = $app['mail_addr_user']->get($user['id'], $app['tschema']);

        $mail_from = $app['s_schema']
            && !$app['s_master']
            && !$app['s_elas_guest']
                ? $app['mail_addr_user']->get($app['s_id'], $app['s_schema'])
                : [];

        $balance = $user['saldo'];

        $data_images = [
            'base_url'    => $app['s3_url'],
        ];

        $st = $app['db']->prepare('select "PictureFile"
            from ' . $app['tschema'] . '.msgpictures
            where msgid = ?');
        $st->bindValue(1, $id);
        $st->execute();

        while ($row = $st->fetch())
        {
            $data_images['files'][] =$row['PictureFile'];
        }

        $and_local = $app['s_guest'] ? ' and local = \'f\' ' : '';

        $prev = $app['db']->fetchColumn('select id
            from ' . $app['tschema'] . '.messages
            where id > ?
            ' . $and_local . '
            order by id asc
            limit 1', [$id]);

        $next = $app['db']->fetchColumn('select id
            from ' . $app['tschema'] . '.messages
            where id < ?
            ' . $and_local . '
            order by id desc
            limit 1', [$id]);

        $title = $message['content'];

        $contacts = $app['db']->fetchAll('select c.*, tc.abbrev
            from ' . $app['tschema'] . '.contact c, ' .
                $app['tschema'] . '.type_contact tc
            where c.id_type_contact = tc.id
                and c.id_user = ?
                and c.flag_public = 1', [$user['id']]);

        $app['assets']->add([
            'leaflet',
            'jssor',
            'messages_show_images_slider.js',
        ]);

        if ($app['s_admin'] || $s_owner)
        {
            $app['assets']->add([
                'fileupload',
                'messages_show_images_upload.js',
            ]);
        }

        if ($app['s_admin'] || $s_owner)
        {
            $app['btn_top']->edit('messages_edit', $app['pp_ary'],
                ['id' => $id],	ucfirst($ow_type) . ' aanpassen');

            $app['btn_top']->del('messages_del', $app['pp_ary'],
                ['id' => $id], ucfirst($ow_type) . ' verwijderen');
        }

        if ($message['is_offer'] === 1
            && ($app['s_admin']
                || (!$s_owner
                    && $user['status'] != 7
                    && !($app['s_guest']
                    && $app['s_system_self']))))
        {
            $tus = ['mid' => $id];

            if (!$app['s_system_self'])
            {
                $tus['tus'] = $app['tschema'];
            }

            $app['btn_top']->add_trans('transactions_add', $app['s_ary'],
                $tus, 'Transactie voor dit aanbod');
        }

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $app['btn_nav']->nav('messages_show', $app['pp_ary'],
            $prev_ary, $next_ary, false);

        $app['btn_nav']->nav_list($app['r_messages'], $app['pp_ary'],
            [], 'Lijst', 'newspaper-o');

        $app['heading']->add(ucfirst($ow_type));
        $app['heading']->add(': ' . htmlspecialchars($message['content'], ENT_QUOTES));
        $app['heading']->add(strtotime($message['validity']) < time() ? ' <small><span class="text-danger">Vervallen</span></small>' : '');
        $app['heading']->fa('newspaper-o');

        if ($message['cid'])
        {
            $out = '<p>Categorie: ';

            $out .= $app['link']->link_no_attr($app['r_messages'], $app['pp_ary'],
                ['f' => ['cid' => $message['cid']]], $message['catname']);

            $out .= '</p>';
        }

        $out .= '<div class="row">';

        $out .= '<div class="col-md-6">';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-body">';

        $out .= '<div id="no_images" ';
        $out .= 'class="text-center center-body" style="display: none;">';
        $out .= '<i class="fa fa-image fa-5x"></i> ';
        $out .= '<p>Er zijn geen afbeeldingen voor ';
        $out .= $ow_type_this . '</p>';
        $out .= '</div>';

        $out .= '<div id="images_con" ';
        $out .= 'data-images="';
        $out .= htmlspecialchars(json_encode($data_images));
        $out .= '">';
        $out .= '</div>';

        $out .= '</div>';

        if ($app['s_admin'] || $s_owner)
        {
            $out .= '<div class="panel-footer">';
            $out .= '<span class="btn btn-success fileinput-button">';
            $out .= '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
            $out .= '<input id="fileupload" type="file" name="images[]" ';
            $out .= 'data-url="';

            $out .= $app['link']->context_path('messages_images_upload',
                $app['pp_ary'], ['id' => $id]);

            $out .= '" ';
            $out .= 'data-data-type="json" data-auto-upload="true" ';
            $out .= 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
            $out .= 'data-max-file-size="999000" ';
            $out .= 'multiple></span>&nbsp;';

            $out .= $app['link']->link_fa('messages_images_del', $app['pp_ary'],
                ['id'		=> $id],
                'Afbeeldingen verwijderen', [
                    'class'	=> 'btn btn-danger',
                    'id'	=> 'btn_remove',
                    'style'	=> 'display:none;',
                ],
                'times'
            );

            $out .= '<p class="text-warning">';
            $out .= 'Afbeeldingen moeten in het jpg/jpeg formaat zijn. ';
            $out .= 'Je kan ook afbeeldingen hierheen verslepen.</p>';
            $out .= '</div>';
        }

        $out .= '</div>';
        $out .= '</div>';
//////////////////////////////////////////////
        $out .= '<div class="col-md-6">';

        $out .= '<div class="panel panel-default printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<p><b>Omschrijving</b></p>';
        $out .= '</div>';
        $out .= '<div class="panel-body">';
        $out .= '<p>';

        if ($message['Description'])
        {
            $out .= htmlspecialchars($message['Description'], ENT_QUOTES);
        }
        else
        {
            $out .= '<i>Er werd geen omschrijving ingegeven.</i>';
        }

        $out .= '</p>';
        $out .= '</div></div>';

        $out .= '<div class="panel panel-default printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl>';
        $out .= '<dt>';
        $out .= '(Richt)prijs';
        $out .= '</dt>';
        $out .= '<dd>';

        if (empty($message['amount']))
        {
            $out .= 'niet opgegeven.';
        }
        else
        {
            $out .= $message['amount'] . ' ';
            $out .= $app['config']->get('currency', $app['tschema']);
            $out .= $message['units'] ? ' per ' . $message['units'] : '';
        }

        $out .= '</dd>';

        $out .= '<dt>Van gebruiker: ';
        $out .= '</dt>';
        $out .= '<dd>';

        $out .= $app['account']->link($message['id_user'], $app['pp_ary']);

        $out .= ' (saldo: <span class="label label-info">';
        $out .= $balance;
        $out .= '</span> ';
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= ')';
        $out .= '</dd>';

        $out .= '<dt>Plaats</dt>';
        $out .= '<dd>';
        $out .= $user['postcode'];
        $out .= '</dd>';

        $out .= '<dt>Aangemaakt op</dt>';
        $out .= '<dd>';
        $out .= $app['date_format']->get($message['cdate'], 'day', $app['tschema']);
        $out .= '</dd>';

        $out .= '<dt>Geldig tot</dt>';
        $out .= '<dd>';
        $out .= $app['date_format']->get($message['validity'], 'day', $app['tschema']);
        $out .= '</dd>';

        if ($app['s_admin'] || $s_owner)
        {
            $out .= '<dt>Verlengen</dt>';
            $out .= '<dd>';
            $out .= self::btn_extend($app['link'], $app['pp_ary'], $id, 30, '1 maand');
            $out .= '&nbsp;';
            $out .= self::btn_extend($app['link'], $app['pp_ary'], $id, 180, '6 maanden');
            $out .= '&nbsp;';
            $out .= self::btn_extend($app['link'], $app['pp_ary'], $id, 365, '1 jaar');
            $out .= '</dd>';
        }

        if ($app['intersystems']->get_count($app['tschema']))
        {
            $out .= '<dt>Zichtbaarheid</dt>';
            $out .= '<dd>';
            $out .=  $app['item_access']->get_label($message['access']);
            $out .= '</dd>';
        }

        $out .= '</dl>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div id="contacts" ';
        $out .= 'data-url="';
/*
        $out .= $app->path('contacts', array_merge(['pp_ary'], [
                'inline'	=> '1',
                'uid'		=> $message['id_user'],
            ]));
*/
        $out .= '"></div>';

    // response form

        if ($app['s_elas_guest'])
        {
            $placeholder = 'Als eLAS gast kan je niet het E-mail formulier gebruiken.';
        }
        else if ($s_owner)
        {
            $placeholder = 'Je kan geen reacties op je eigen berichten sturen.';
        }
        else if (!count($mail_to))
        {
            $placeholder = 'Er is geen E-mail adres bekend van deze gebruiker.';
        }
        else if (!count($mail_from))
        {
            $placeholder = 'Om het E-mail formulier te gebruiken moet een E-mail adres ingesteld zijn voor je eigen Account.';
        }
        else
        {
            $placeholder = '';
        }

        $disabled = (!$app['s_schema'] || !count($mail_to) || !count($mail_from) || $s_owner) ? true : false;

        $out .= '<h3><i class="fa fa-envelop-o"></i> Stuur een reactie naar ';
        $out .=  $app['account']->link($message['id_user'], $app['pp_ary']);
        $out .= '</h3>';
        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<textarea name="content" rows="6" placeholder="';
        $out .= $placeholder;
        $out .= '" ';
        $out .= 'class="form-control" required';
        $out .= $disabled ? ' disabled' : '';
        $out .= '>';
        $out .= $content ?? '';
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label class="control-label" for="mail_cc">';
        $out .= '<input type="checkbox" name="cc" ';
        $out .= 'id="mail_cc" value="1"';
        $out .= $cc ? ' checked="checked"' : '';
        $out .= '> Stuur een kopie naar mijzelf';
        $out .= '</label>';
        $out .= '</div>';

        $out .= '<input type="submit" name="mail" ';
        $out .= 'value="Versturen" class="btn btn-default"';
        $out .= $disabled ? ' disabled' : '';
        $out .= '>';
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('messages');

        return $app['tpl']->get();
    }

    static public function btn_extend(
        link $link,
        array $pp_ary,
        int $id,
        int $days,
        string $label
    ):string
    {
        return $link->link('messages_extend', $pp_ary, [
                'id' 	=> $id,
                'days' 	=> $days,
            ], $label, [
                'class' => 'btn btn-default',
            ]);
    }

    public static function get_message(db $db, int $id, string $tschema):array
    {
        $message = $db->fetchAssoc('select m.*,
                c.id as cid,
                c.fullname as catname
            from ' . $tschema . '.messages m, ' .
                $tschema . '.categories c
            where m.id = ?
                and c.id = m.id_category', [$id]);

        if (!$message)
        {
            throw new NotFoundHttpException('Dit bericht bestaat niet of werd verwijderd.');
        }

        $message['access'] = cnst_access::FROM_LOCAL[$message['local']];

        $message['is_offer'] = $message['msg_type'] === 1;
        $message['is_want'] = $message['msg_type'] === 0;

        return $message;
    }
}
