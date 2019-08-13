<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\contacts_edit;
use cnst\access as cnst_access;

class contacts_add
{
    public function users(Request $request, app $app, int $user_id):Response
    {
        return $this->match_admin($request, $app, 'users');
    }

    public function admin(Request $request, app $app):Response
    {
        if($request->isMethod('POST'))
        {
            $errors = [];

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            $letscode = $request->request->get('letscode', '');
            [$letscode] = explode(' ', trim($letscode));

            $user_id = $app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.users
                where letscode = ?', [$letscode]);

            if ($user_id)
            {
                $letscode = $app['account']->str($user_id, $app['tschema']);
            }
            else
            {
                $errors[] = 'Ongeldige Account Code.';
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
                'id_user'				=> $user_id,
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
                $errors[] = 'Ongeldig contact type!';
            }

            $mail_type_id = $app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.type_contact
                where abbrev = \'mail\'');

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

                if ($app['db']->insert($app['tschema'] . '.contact', $contact))
                {
                    $app['alert']->success('Contact opgeslagen.');
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
            $contact = [
                'value'				=> '',
                'comments'			=> '',
            ];

            $access = '';
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

        $app['heading']->add('Contact toevoegen');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="letscode" class="control-label">Voor</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="fcode_addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control" id="letscode" name="letscode" ';

        $out .= 'data-typeahead="';
        $out .= $app['typeahead']->ini($app['pp_ary'])
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'inactive'])
            ->add('accounts', ['status' => 'ip'])
            ->add('accounts', ['status' => 'im'])
            ->add('accounts', ['status' => 'extern'])
            ->str([
                'filter'        => 'accounts',
                'newuserdays'   => $app['config']->get('newuserdays', $app['tschema']),
            ]);
        $out .= '" ';

        $out .= 'placeholder="Account Code" ';
        $out .= 'value="';
        $out .= $letscode;
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

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
        $out .= contacts_edit::FORMAT[$abbrev]['fa'] ?? 'circle-o';
        $out .= '"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="value" name="value" ';
        $out .= 'value="';
        $out .= $contact['value'];
        $out .= '" required disabled maxlength="130" ';
        $out .= 'data-contacts-format="';
        $out .= htmlspecialchars(json_encode(contacts_edit::FORMAT));
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

        $out .= $app['item_access']->get_radio_buttons('access', $access, 'contacts_add');

        $out .= $app['link']->btn_cancel('contacts', $app['pp_ary'], []);

        $out .= '&nbsp;';

        $out .= '<input type="submit" value="Opslaan" ';
        $out .= 'name="zend" class="btn btn-success">';

        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('contacts');

        return $app['tpl']->get();
    }
}
