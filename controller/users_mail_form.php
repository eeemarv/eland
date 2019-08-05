<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class users_mail_form
{
    public function inline_for_profile(Request $request, app $app, int $user_id):Response
    {
        $mail_to = $app['mail_addr_user']->get($user_id, $app['tschema']);

        $mail_from = $app['s_schema']
            && !$app['s_master']
            && !$app['s_elas_guest']
                ? $app['mail_addr_user']->get($app['s_id'], $app['s_schema'])
                : [];

        if ($request->request->get('user_mail_submit') && $request->isMethod('POST'))
        {
            $user_mail_content = $request->request->get('user_mail_content', '');
            $user_mail_cc = $request->request->('user_mail_cc', '') ? true : false;

            $to_user = $app['user_cache']->get($user_id, $app['tschema']);

            if (!$app['s_admin'] && !in_array($to_user['status'], [1, 2]))
            {
                $app['alert']->error('Je hebt geen rechten
                    om een E-mail bericht naar een niet-actieve
                    gebruiker te sturen');
                $app['link']->redirect($app['r_users'], $app['pp_ary'], []);
            }

            if ($app['s_master'])
            {
                $app['alert']->error('Het master account kan
                    geen E-mail berichten versturen.');
                $app['link']->redirect('users_show', $app['pp_ary'], ['id' => $user_id]);
            }

            if (!$app['s_schema'])
            {
                $app['alert']->error('Je hebt onvoldoende
                    rechten om een E-mail bericht te versturen.');
                $app['link']->redirect('users', $app['pp_ary'], ['id' => $user_id]);
            }

            if (!$user_mail_content)
            {
                $app['alert']->error('Fout: leeg bericht. E-mail niet verzonden.');
                $app['link']->redirect('users_show', $app['pp_ary'], ['id' => $user_id]);
            }

            $reply_ary = $app['mail_addr_user']->get($app['s_id'], $app['s_schema']);

            if (!count($reply_ary))
            {
                $app['alert']->error('Fout: Je kan geen berichten naar andere gebruikers
                    verzenden als er geen E-mail adres is ingesteld voor je eigen account.');
                $app['link']->redirect('users', $app['pp_ary'], ['id' => $user_id]);
            }

            $from_contacts = $app['db']->fetchAll('select c.value, tc.abbrev
                from ' . $app['s_schema'] . '.contact c, ' .
                    $app['s_schema'] . '.type_contact tc
                where c.flag_public >= ?
                    and c.id_user = ?
                    and c.id_type_contact = tc.id',
                    [cnst::ACCESS_ARY[$to_user['accountrole']], $app['s_id']]);

            $from_user = $app['user_cache']->get($app['s_id'], $app['s_schema']);

            $vars = [
                'from_contacts'		=> $from_contacts,
                'from_user'			=> $from_user,
                'from_schema'		=> $app['s_schema'],
                'to_user'			=> $to_user,
                'to_schema'			=> $app['tschema'],
                'is_same_system'	=> $app['s_system_self'],
                'msg_content'		=> $user_mail_content,
            ];

            $mail_template = $app['s_system_self']
                ? 'user_msg/msg'
                : 'user_msg/msg_intersystem';

            $app['queue.mail']->queue([
                'schema'	=> $app['tschema'],
                'to'		=> $app['mail_addr_user']->get($user_id, $app['tschema']),
                'reply_to'	=> $reply_ary,
                'template'	=> $mail_template,
                'vars'		=> $vars,
            ], 8000);

            if ($user_mail_cc)
            {
                $mail_template = $app['s_system_self']
                    ? 'user_msg/copy'
                    : 'user_msg/copy_intersystem';

                $app['queue.mail']->queue([
                    'schema'	=> $app['tschema'],
                    'to' 		=> $app['mail_addr_user']->get($app['s_id'], $app['s_schema']),
                    'template' 	=> $mail_template,
                    'vars'		=> $vars,
                ], 8000);
            }

            $app['alert']->success('E-mail bericht verzonden.');
            $app['link']->redirect('users', $app['pp_ary'], ['id' => $user_id]);

        }

        $s_owner = !$app['s_guest']
            && $app['s_system_self']
            && $app['s_id'] == $user_id
            && $user_id;

        $mail_to = $app['mail_addr_user']->get($user['id'], $app['tschema']);
        $mail_from = $app['s_schema']
            && !$app['s_master']
            && !$app['s_elas_guest']
                ? $app['mail_addr_user']->get($app['s_id'], $app['s_schema'])
                : [];

        $placeholder = '';

        if ($app['s_elas_guest'])
        {
            $placeholder = 'Als eLAS gast kan je niet het E-mail formulier gebruiken.';
        }
        else if ($s_owner)
        {
            $placeholder = 'Je kan geen E-mail berichten naar jezelf verzenden.';
        }
        else if (!count($mail_to))
        {
            $placeholder = 'Er is geen E-mail adres bekend van deze gebruiker.';
        }
        else if (!count($mail_from))
        {
            $placeholder = 'Om het E-mail formulier te gebruiken moet een E-mail adres ingesteld zijn voor je eigen Account.';
        }

        $disabled = !$app['s_schema']
            || !count($mail_to)
            || !count($mail_from)
            || $s_owner;

        $out = '<h3><i class="fa fa-envelop-o"></i> ';
        $out .= 'Stuur een bericht naar ';
        $out .=  $app['account']->link($user_id, $app['pp_ary']);
        $out .= '</h3>';
        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post"">';

        $out .= '<div class="form-group">';
        $out .= '<textarea name="user_mail_content" rows="6" placeholder="';
        $out .= $placeholder . '" ';
        $out .= 'class="form-control" required';
        $out .= $disabled ? ' disabled' : '';
        $out .= '>';
        $out .= $user_mail_content ?? '';
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="user_mail_cc" class="control-label">';
        $out .= '<input type="checkbox" name="user_mail_cc" ';
        $out .= 'id="user_mail_cc" value="1"';
        $out .= $user_mail_cc ? ' checked="checked"' : '';
        $out .= '> Stuur een kopie naar mijzelf';
        $out .= '</label>';
        $out .= '</div>';

        $out .= '<input type="submit" name="user_mail_submit" ';
        $out .= 'value="Versturen" class="btn btn-default"';
        $out .= $disabled ? ' disabled' : '';
        $out .= '>';

        $out .= '</form>';

        $out .= '</div>';
        $out = '</div>';

        return new Response($out);
    }

    public function inline_for_message(Request $request, app $app, int $user_id, int $msg_id):Response
    {
        return new Response('');
    }
}
