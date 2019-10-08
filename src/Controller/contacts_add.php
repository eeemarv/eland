<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\contacts_edit;
use cnst\access as cnst_access;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class contacts_add
{
    public function contacts_add_admin(Request $request, app $app):Response
    {
        return self::form($request, $app, 0, true);
    }

    public static function form(Request $request, app $app, int $user_id, bool $redirect_contacts):Response
    {
        $account_code = $request->request->get('account_code', '');
        $id_type_contact = (int) $request->request->get('id_type_contact', '');
        $value = $request->request->get('value', '');
        $comments = $request->request->get('comments', '');
        $access = $request->request->get('access', '');

        if($request->isMethod('POST'))
        {
            $errors = [];

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            if ($app['pp_admin'] && $redirect_contacts)
            {
               [$code] = explode(' ', trim($account_code));

                $user_id = $app['db']->fetchColumn('select id
                    from ' . $app['pp_schema'] . '.users
                    where letscode = ?', [$code]);

                if (!$user_id)
                {
                    $errors[] = 'Ongeldige Account Code.';
                }
            }

            if (!$value)
            {
                $errors[] = 'Vul waarde in!';
            }

            if (!$access)
            {
                $errors[] = 'Vul zichtbaarheid in.';
            }

            if (!isset(cnst_access::TO_FLAG_PUBLIC[$access]))
            {
                throw new BadRequestHttpException('Ongeldige waarde zichtbaarheid');
            }

            $abbrev_type = $app['db']->fetchColumn('select abbrev
                from ' . $app['pp_schema'] . '.type_contact
                where id = ?', [$id_type_contact]);

            if(!$abbrev_type)
            {
                throw new BadRequestHttpException('Ongeldig contact type!');
            }

            if ($abbrev_type === 'mail'
                && !filter_var($value, FILTER_VALIDATE_EMAIL))
            {
                $errors[] = 'Geen geldig E-mail adres';
            }

            if (strlen($value) > 130)
            {
                $errors[] = 'De waarde mag maximaal 130 tekens lang zijn.';
            }

            if (strlen($comments) > 50)
            {
                $errors[] = 'Commentaar mag maximaal 50 tekens lang zijn.';
            }

            $mail_type_id = $app['db']->fetchColumn('select id
                from ' . $app['pp_schema'] . '.type_contact
                where abbrev = \'mail\'');

            if ($id_type_contact === $mail_type_id)
            {
                $mailadr = $value;

                $mail_count = $app['db']->fetchColumn('select count(c.*)
                    from ' . $app['pp_schema'] . '.contact c, ' .
                        $app['pp_schema'] . '.type_contact tc, ' .
                        $app['pp_schema'] . '.users u
                    where c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'
                        and c.id_user = u.id
                        and u.status in (1, 2)
                        and u.id <> ?
                        and c.value = ?', [$user_id, $mailadr]);

                if ($mail_count && $app['pp_admin'])
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
                        'adr'		=> $value,
                        'uid'		=> $user_id,
                        'schema'	=> $app['pp_schema'],
                    ], 0);
                }

                $insert_ary = [
                    'id_type_contact'		=> $id_type_contact,
                    'value'					=> $value,
                    'comments' 				=> $comments,
                    'flag_public'			=> cnst_access::TO_FLAG_PUBLIC[$access],
                    'id_user'				=> $user_id,
                ];

                if ($app['db']->insert($app['pp_schema'] . '.contact', $insert_ary))
                {
                    $app['alert']->success('Contact opgeslagen.');

                    if ($redirect_contacts)
                    {
                        $app['link']->redirect('contacts', $app['pp_ary'], []);
                    }

                    $app['link']->redirect('users_show', $app['pp_ary'],
                        ['id' => $user_id]);

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

        $tc = [];

        $rs = $app['db']->prepare('select id, name, abbrev
            from ' . $app['pp_schema'] . '.type_contact');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $tc[$row['id']] = $row;

            if ($id_type_contact)
            {
                continue;
            }

            $id_type_contact = $row['id'];
        }

        $app['assets']->add(['contacts_edit.js']);

        $abbrev = $tc[$id_type_contact]['abbrev'];

        $app['heading']->add('Contact toevoegen');

        if ($app['pp_admin'] && !$redirect_contacts)
        {
            $app['heading']->add(' voor ');
            $app['heading']->add_raw($app['account']->link($user_id, $app['pp_ary']));
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        if ($app['pp_admin'] && $redirect_contacts)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="account_code" class="control-label">Voor</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon" id="fcode_addon">';
            $out .= '<span class="fa fa-user"></span></span>';
            $out .= '<input type="text" class="form-control" id="account_code" name="account_code" ';

            $out .= 'data-typeahead="';
            $out .= $app['typeahead']->ini($app['pp_ary'])
                ->add('accounts', ['status' => 'active'])
                ->add('accounts', ['status' => 'inactive'])
                ->add('accounts', ['status' => 'ip'])
                ->add('accounts', ['status' => 'im'])
                ->add('accounts', ['status' => 'extern'])
                ->str([
                    'filter'        => 'accounts',
                    'newuserdays'   => $app['config']->get('newuserdays', $app['pp_schema']),
                ]);
            $out .= '" ';

            $out .= 'placeholder="Account Code" ';
            $out .= 'value="';
            $out .= $account_code;
            $out .= '" required>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= '<label for="id_type_contact" class="control-label">Type</label>';
        $out .= '<select name="id_type_contact" id="id_type_contact" ';
        $out .= 'class="form-control" required>';

        foreach ($tc as $id_tc => $type)
        {
            $out .= '<option value="';
            $out .= $id_tc;
            $out .= '" ';
            $out .= 'data-abbrev="';
            $out .= $type['abbrev'];
            $out .= '" ';
            $out .= $id_tc === $id_type_contact ? ' selected="selected"' : '';
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
        $out .= $value;
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
        $out .= $comments;
        $out .= '" maxlength="50">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $app['item_access']->get_radio_buttons('access', $access, 'contacts_add');

        if ($redirect_contacts)
        {
           $out .= $app['link']->btn_cancel('contacts', $app['pp_ary'], []);
        }
        else
        {
            $out .= $app['link']->btn_cancel('users_show', $app['pp_ary'],
                ['id' => $user_id]);
        }

        $out .= '&nbsp;';

        $out .= '<input type="submit" value="Opslaan" ';
        $out .= 'name="zend" class="btn btn-success btn-lg">';

        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set($redirect_contacts ? 'contacts' : 'users');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
