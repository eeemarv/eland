<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use cnst\access as cnst_access;

class contacts_edit
{
    const FORMAT = [
        'adr'	=> [
            'fa'		=> 'map-marker',
            'lbl'		=> 'Adres',
            'explain'	=> 'Voorbeeldstraat 23, 4520 Voorbeeldgemeente',
        ],
        'gsm'	=> [
            'fa'		=> 'mobile',
            'lbl'		=> 'GSM',
        ],
        'tel'	=> [
            'fa'		=> 'phone',
            'lbl'		=> 'Telefoon',
        ],
        'mail'	=> [
            'fa'		=> 'envelope-o',
            'lbl'		=> 'E-mail',
            'type'		=> 'email',
        ],
        'web'	=> [
            'fa'		=> 'link',
            'lbl'		=> 'Website',
            'type'		=> 'url',
        ],
    ];

    public function match(Request $request, app $app, int $id):Response
    {
        if (!($user_id = $app['db']->fetchColumn('select id_user
            from ' . $app['tschema'] . '.contact
            where id = ?', [$id])))
        {
            $app['alert']->error('Dit contact heeft geen eigenaar
                of bestaat niet.');
            $app['link']->redirect('contacts', $app['pp_ary'], []);
        }

        if($request->isMethod('POST'))
        {
            $errors = [];

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            $access = $request->request->get('access', '');

            if ($access)
            {
                $flag_public = cnst_access::TO_FLAG_PUBLIC[$access];
            }
            else
            {
                $errors[] = 'Vul een zichtbaarheid in!';
                $flag_public = 2;
            }

            $contact = [
                'id_type_contact'		=> $request->request->get('id_type_contact'),
                'value'					=> $request->request->get('value'),
                'comments' 				=> $request->request->get('comments'),
                'flag_public'			=> $flag_public,
            ];

            $abbrev_type = $app['db']->fetchColumn('select abbrev
                from ' . $app['tschema'] . '.type_contact
                where id = ?', [$contact['id_type_contact']]);

            if ($abbrev_type === 'mail'
                && !filter_var($contact['value'], FILTER_VALIDATE_EMAIL))
            {
                $errors[] = 'Geen geldig E-mail adres';
            }

            if (!$contact['value'])
            {
                $errors[] = 'Vul waarde in!';
            }

            if (strlen($contact['value']) > 130)
            {
                $errors[] = 'De waarde mag maximaal 130 tekens lang zijn.';
            }

            if (strlen($contact['comments']) > 50)
            {
                $errors[] = 'Commentaar mag maximaal 50 tekens lang zijn.';
            }

            if(!$abbrev_type)
            {
                $errors[] = 'Contact type bestaat niet!';
            }

            $mail_type_id = $app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.type_contact
                where abbrev = \'mail\'');

            $count_mail = $app['db']->fetchColumn('select count(*)
                from ' . $app['tschema'] . '.contact
                where id_user = ?
                    and id_type_contact = ?',
                [$user_id, $mail_type_id]);

            $mail_id = $app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.contact
                where id_user = ?
                    and id_type_contact = ?',
                [$user_id, $mail_type_id]);

            if ($id == $mail_id && $count_mail == 1 && $contact['id_type_contact'] != $mail_type_id)
            {
                $app['alert']->warning('Waarschuwing: de gebruiker heeft
                    geen E-mail adres.');
            }

            if ($contact['id_type_contact'] == $mail_type_id)
            {
                $mailadr = $contact['value'];

                $mail_count = $app['db']->fetchColumn('select count(c.*)
                    from ' . $app['tschema'] . '.contact c, ' .
                        $app['tschema'] . '.type_contact tc, ' .
                        $app['tschema'] . '.users u
                    where c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'
                        and c.id_user = u.id
                        and u.status in (1, 2)
                        and u.id <> ?
                        and c.value = ?', [$user_id, $mailadr]);

                if ($mail_count && $app['s_admin'])
                {
                    $warning = 'Omdat deze gebruikers niet meer ';
                    $warning .= 'een uniek E-mail adres hebben zullen zij ';
                    $warning .= 'niet meer zelf hun paswoord kunnnen resetten ';
                    $warning .= 'of kunnen inloggen met ';
                    $warning .= 'E-mail adres. Zie ';
                    $warning .= $app['link']->link_no_attr('status',
                        $app['pp_ary'], [], 'Status');

                    if ($mail_count == 1)
                    {
                        $warning_2 = 'Waarschuwing: E-mail adres ' . $mailadr;
                        $warning_2 .= ' bestaat al onder de actieve gebruikers.';
                    }
                    else if ($mail_count > 1)
                    {
                        $warning_2 = 'Waarschuwing: E-mail adres ' . $mailadr;
                        $warning_2 .= ' bestaat al ' . $mail_count;
                        $warning_2 .= ' maal onder de actieve gebruikers.';
                    }

                    $app['alert']->warning($warning_2 . ' ' . $warning);
                }
                else if ($mail_count)
                {
                    $errors[] = 'Dit E-mail adres komt reeds voor onder
                        de actieve gebruikers.';
                }
            }

            if(!count($errors))
            {
                if ($abbrev_type === 'adr')
                {
                    $app['queue.geocode']->cond_queue([
                        'adr'		=> $contact['value'],
                        'uid'		=> $contact['id_user'],
                        'schema'	=> $app['tschema'],
                    ], 0);
                }

                if ($app['db']->update($app['tschema'] . '.contact',
                    $contact, ['id' => $id]))
                {
                    $app['alert']->success('Contact aangepast.');
                    $app['link']->redirect('contacts', $app['pp_ary'], []);
                }
                else
                {
                    $app['alert']->error('Fout bij het opslaan');
                }
            }
            else
            {
                $app['alert']->error($errors);
            }
        }
        else
        {
            $contact = $app['db']->fetchAssoc('select *
                from ' . $app['tschema'] . '.contact
                where id = ?', [$id]);

            $access = cnst_access::FROM_FLAG_PUBLIC[$contact['flag_public']];
        }

        $tc = [];

        $rs = $app['db']->prepare('select id, name, abbrev
            from ' . $app['tschema'] . '.type_contact');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $tc[$row['id']] = $row;

            if (isset($contact['id_type_contact']))
            {
                continue;
            }

            $contact['id_type_contact'] = $row['id'];
        }

        $app['assets']->add(['contacts_edit.js']);

        $abbrev = $tc[$contact['id_type_contact']]['abbrev'];

        $app['heading']->add('Contact aanpassen');
        $app['heading']->add(' voor ');
        $app['heading']->add($app['account']->link($user_id, $app['pp_ary']));

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="id_type_contact" class="control-label">Type</label>';
        $out .= '<select name="id_type_contact" id="id_type_contact" ';
        $out .= 'class="form-control" required>';

        foreach ($tc as $id => $type)
        {
            $out .= '<option value="';
            $out .= $id;
            $out .= '" ';
            $out .= 'data-abbrev="';
            $out .= $type['abbrev'];
            $out .= '" ';
            $out .= $id == $contact['id_type_contact'] ? ' selected="selected"' : '';
            $out .= '>';
            $out .= $type['name'];
            $out .= '</option>';
        }

        $out .= "</select>";
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="value" class="control-label">';
        $out .= 'Waarde</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="value_addon">';
        $out .= '<i class="fa fa-';
        $out .= self::FORMAT[$abbrev]['fa'] ?? 'circle-o';
        $out .= '"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="value" name="value" ';
        $out .= 'value="';
        $out .= $contact['value'];
        $out .= '" required disabled maxlength="130" ';
        $out .= 'data-contacts-format="';
        $out .= htmlspecialchars(json_encode(self::FORMAT));
        $out .= '">';
        $out .= '</div>';
        $out .= '<p id="contact-explain">';

        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="comments" class="control-label">';
        $out .= 'Commentaar</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-comment-o"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="comments" name="comments" ';
        $out .= 'value="';
        $out .= $contact['comments'];
        $out .= '" maxlength="50">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $app['item_access']->get_radio_buttons('access', $access);

        $out .= $app['link']->btn_cancel('contacts', $app['pp_ary'], []);

        $out .= '&nbsp;';

        $out .= '<input type="submit" value="Aanpassen" ';
        $out .= 'name="zend" class="btn btn-primary">';

        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('contacts');

        return $app['tpl']->get($request);
    }
}
