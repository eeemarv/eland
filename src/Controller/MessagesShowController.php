<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Doctrine\DBAL\Connection as Db;
use App\Render\LinkRender;
use App\Cnst\AccessCnst;
use App\Cnst\MessageTypeCnst;
use controller\contacts_user_show_inline;
use controller\users_show;

class MessagesShowController extends AbstractController
{
    public function messages_show(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $message = self::get_message($db, $id, $app['pp_schema']);

        $user_mail_content = $request->request->get('user_mail_content', '');
        $user_mail_cc = $request->request->get('user_mail_cc', '') ? true : false;
        $user_mail_submit = $request->request->get('user_mail_submit', '') ? true : false;

        $user_mail_cc = $request->isMethod('POST') ? $user_mail_cc : true;

        if ($message['access'] === 'user' && $app['pp_guest'])
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot dit bericht.');
        }

        $s_owner = !$app['pp_guest']
            && $app['s_system_self']
            && $app['s_id'] === $message['id_user']
            && $message['id_user'] > 0;

        $user = $app['user_cache']->get($message['id_user'], $app['pp_schema']);

        // process mail form

        if ($user_mail_submit && $request->isMethod('POST'))
        {
            $errors = [];

            $to_user = $user;

            if (!$app['pp_admin'] && !in_array($to_user['status'], [1, 2]))
            {
                throw new AccessDeniedHttpException('Je hebt geen rechten om een
                    bericht naar een niet-actieve gebruiker te sturen');
            }

            if ($app['s_master'])
            {
                throw new AccessDeniedHttpException('Het master account
                    kan geen berichten versturen.');
            }

            if (!$app['s_schema'] || $app['s_elas_guest'])
            {
                throw new AccessDeniedHttpException('Je hebt onvoldoende rechten
                    om een E-mail bericht te versturen.');
            }

            $token_error = $app['form_token']->get_error();

            if ($token_error)
            {
                $errors[] = $token_error;
            }

            if (!$user_mail_content)
            {
                $errors[] = 'Fout: leeg bericht. E-mail niet verzonden.';
            }

            $reply_ary = $app['mail_addr_user']->get_active($app['s_id'], $app['s_schema']);

            if (!count($reply_ary))
            {
                $errors[] = 'Fout: Je kan geen berichten naar een andere gebruiker
                    verzenden als er geen E-mail adres is ingesteld voor je eigen account.';
            }

            if (!count($errors))
            {
                $from_contacts = $db->fetchAll('select c.value, tc.abbrev
                    from ' . $app['s_schema'] . '.contact c, ' .
                        $app['s_schema'] . '.type_contact tc
                    where c.flag_public >= ?
                        and c.id_user = ?
                        and c.id_type_contact = tc.id',
                        [AccessCnst::TO_FLAG_PUBLIC[$to_user['accountrole']], $app['s_id']]);

                $from_user = $app['user_cache']->get($app['s_id'], $app['s_schema']);

                $vars = [
                    'from_contacts'		=> $from_contacts,
                    'from_user'			=> $from_user,
                    'from_schema'		=> $app['s_schema'],
                    'is_same_system'	=> $app['s_system_self'],
                    'to_user'			=> $to_user,
                    'to_schema'			=> $app['pp_schema'],
                    'msg_content'		=> $user_mail_content,
                    'message'			=> $message,
                ];

                $mail_template = $app['s_system_self']
                    ? 'message_msg/msg'
                    : 'message_msg/msg_intersystem';

                $app['queue.mail']->queue([
                    'schema'	=> $app['pp_schema'],
                    'to'		=> $app['mail_addr_user']->get_active($to_user['id'], $app['pp_schema']),
                    'reply_to'	=> $reply_ary,
                    'template'	=> $mail_template,
                    'vars'		=> $vars,
                ], 8500);

                if ($user_mail_cc)
                {
                    $mail_template = $app['s_system_self']
                        ? 'message_msg/copy'
                        : 'message_msg/copy_intersystem';

                    $app['queue.mail']->queue([
                        'schema'	=> $app['pp_schema'],
                        'to'		=> $app['mail_addr_user']->get_active($app['s_id'], $app['s_schema']),
                        'template'	=> $mail_template,
                        'vars'		=> $vars,
                    ], 8000);
                }

                $app['alert']->success('Mail verzonden.');
                $app['link']->redirect('messages_show', $app['pp_ary'],
                    ['id' => $id]);
            }

            $app['alert']->error($errors);
        }

        $balance = $user['saldo'];

        $data_images = [
            'base_url'      => $app['s3_url'],
            'files'         => [],
        ];

        $st = $db->prepare('select "PictureFile"
            from ' . $app['pp_schema'] . '.msgpictures
            where msgid = ?');
        $st->bindValue(1, $id);
        $st->execute();

        while ($row = $st->fetch())
        {
            $data_images['files'][] =$row['PictureFile'];
        }

        $and_local = $app['pp_guest'] ? ' and local = \'f\' ' : '';

        $prev = $db->fetchColumn('select id
            from ' . $app['pp_schema'] . '.messages
            where id > ?
            ' . $and_local . '
            order by id asc
            limit 1', [$id]);

        $next = $db->fetchColumn('select id
            from ' . $app['pp_schema'] . '.messages
            where id < ?
            ' . $and_local . '
            order by id desc
            limit 1', [$id]);

        $contacts_user_show_inline = new contacts_user_show_inline();
        $contacts_response = $contacts_user_show_inline->contacts_user_show_inline($app, $user['id']);
        $contacts_content = $contacts_response->getContent();

        $app['assets']->add([
            'jssor',
            'messages_show_images_slider.js',
        ]);

        if ($app['pp_admin'] || $s_owner)
        {
            $app['assets']->add([
                'fileupload',
                'messages_show_images_upload.js',
            ]);
        }

        if ($app['pp_admin'] || $s_owner)
        {
            $app['btn_top']->edit('messages_edit', $app['pp_ary'],
                ['id' => $id],	ucfirst($message['label']['type']) . ' aanpassen');

            $app['btn_top']->del('messages_del', $app['pp_ary'],
                ['id' => $id], ucfirst($message['label']['type']) . ' verwijderen');
        }

        if ($message['is_offer']
            && ($app['pp_admin']
                || (!$s_owner
                    && $user['status'] !== 7
                    && !($app['pp_guest'] && $app['s_system_self']))))
        {
            $tus = ['mid' => $id];

            if (!$app['s_system_self'])
            {
                $tus['tus'] = $app['pp_schema'];
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

        $app['heading']->add(ucfirst($message['label']['type']));
        $app['heading']->add(': ' . $message['content']);
        $app['heading']->add_raw(strtotime($message['validity']) < time() ? ' <small><span class="text-danger">Vervallen</span></small>' : '');
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
        $out .= 'class="text-center center-body">';
        $out .= '<i class="fa fa-image fa-5x"></i> ';
        $out .= '<p>Er zijn geen afbeeldingen voor ';
        $out .= $message['label']['type_this'] . '</p>';
        $out .= '</div>';

        $out .= '<div id="images_con" ';
        $out .= 'data-images="';
        $out .= htmlspecialchars(json_encode($data_images));
        $out .= '">';
        $out .= '</div>';

        $out .= '</div>';

        if ($app['pp_admin'] || $s_owner)
        {
            $out .= '<div class="panel-footer">';
            $out .= '<span class="btn btn-success btn-lg btn-block fileinput-button">';
            $out .= '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
            $out .= '<input id="fileupload" type="file" name="images[]" ';
            $out .= 'data-url="';

            $out .= $app['link']->context_path('messages_images_upload',
                $app['pp_ary'], ['id' => $id]);

            $out .= '" ';
            $out .= 'data-data-type="json" data-auto-upload="true" ';
            $out .= 'data-accept-file-types="/(\.|\/)(jpe?g|png|gif)$/i" ';
            $out .= 'data-max-file-size="999000" ';
            $out .= 'multiple></span>';

            $out .= '<p class="text-warning">';
            $out .= 'Toegestane formaten: jpg/jpeg, png, gif. ';
            $out .= 'Je kan ook afbeeldingen hierheen verslepen.</p>';

            $out .= $app['link']->link_fa('messages_images_del', $app['pp_ary'],
                ['id'		=> $id],
                'Afbeeldingen verwijderen', [
                    'class'	=> 'btn btn-danger btn-lg btn-block',
                    'id'	=> 'btn_remove',
                    'style'	=> 'display:none;',
                ],
                'times'
            );

            $out .= '</div>';
        }

        $out .= '</div>';
        $out .= '</div>';

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
            $out .= $app['config']->get('currency', $app['pp_schema']);
            $out .= $message['units'] ? ' per ' . $message['units'] : '';
        }

        $out .= '</dd>';

        $out .= '<dt>Van gebruiker: ';
        $out .= '</dt>';
        $out .= '<dd>';
        $out .= $app['account']->link($message['id_user'], $app['pp_ary']);
        $out .= '</dd>';

        $out .= '<dt>Plaats</dt>';
        $out .= '<dd>';
        $out .= $user['postcode'];
        $out .= '</dd>';

        $out .= '<dt>Aangemaakt op</dt>';
        $out .= '<dd>';
        $out .= $app['date_format']->get($message['cdate'], 'day', $app['pp_schema']);
        $out .= '</dd>';

        $out .= '<dt>Geldig tot</dt>';
        $out .= '<dd>';
        $out .= $app['date_format']->get($message['validity'], 'day', $app['pp_schema']);
        $out .= '</dd>';

        if ($app['pp_admin'] || $s_owner)
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

        if ($app['intersystems']->get_count($app['pp_schema']))
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

        $out .= users_show::get_mail_form($app, $message['id_user'], $user_mail_content, $user_mail_cc);

        $out .= $contacts_content;

        $app['menu']->set('messages');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
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

    public static function get_message(Db $db, int $id, string $pp_schema):array
    {
        $message = $db->fetchAssoc('select m.*,
                c.id as cid,
                c.fullname as catname
            from ' . $pp_schema . '.messages m, ' .
                $pp_schema . '.categories c
            where m.id = ?
                and c.id = m.id_category', [$id]);

        if (!$message)
        {
            throw new NotFoundHttpException('Dit bericht bestaat niet of werd verwijderd.');
        }

        $message['access'] = AccessCnst::FROM_LOCAL[$message['local']];

        $message['type'] = MessageTypeCnst::FROM_DB[$message['msg_type']];
        $message['is_offer'] = $message['type'] === 'offer';
        $message['is_want'] = $message['type'] === 'want';

        $message['label'] = self::get_label($message['type']);

        return $message;
    }

    public static function get_label(string $type):array
    {
        return [
            'type'  => MessageTypeCnst::TO_LABEL[$type],
            'type_the'  => MessageTypeCnst::TO_THE_LABEL[$type],
            'type_this' => MessageTypeCnst::TO_THIS_LABEL[$type],
        ];
    }
}