<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use cnst\status as cnst_status;
use cnst\access as cnst_access;
use cnst\role as cnst_role;
use controller\users_list;

class users_show
{
    public function users_show(Request $request, app $app, string $status, int $id):Response
    {
        return $this->users_show_admin($request, $app, $status, $id);
    }

    public function users_show_admin(Request $request, app $app, string $status, int $id):Response
    {
        $tdays = $request->query->get('tdays', '365');

        $user_mail_content = $request->request->get('user_mail_content', '');
        $user_mail_cc = $request->request->get('user_mail_cc', '') ? true : false;
        $user_mail_submit = $request->request->get('user_mail_submit', '') ? true : false;

        $s_owner = !$app['s_guest']
            && $app['s_system_self']
            && $app['s_id'] == $id
            && $id;

        $user_mail_cc = $request->isMethod('POST') ? $user_mail_cc : 1;

        $user = $app['user_cache']->get($id, $app['tschema']);

        if (!$user)
        {
            throw new NotFoundHttpException(
                sprintf('De gebruiker met id %1$d bestaat niet', $id));
        }

        if (!$app['s_admin'] && !in_array($user['status'], [1, 2]))
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot deze gebruiker.');
        }

        $status_def_ary = users_list::get_status_def_ary($app['s_admin'], $app['new_user_treshold']);

        $mail_to = $app['mail_addr_user']->get($id, $app['tschema']);
        $mail_from = $app['s_schema']
            && !$app['s_master']
            && !$app['s_elas_guest']
                ? $app['mail_addr_user']->get($app['s_id'], $app['s_schema'])
                : [];

        // process mail form

        if ($request->isMethod('POST') && $user_mail_submit)
        {
            $errors = [];

            if ($app['s_master'])
            {
                throw new AccessDeniedHttpException('Het master account kan
                    geen E-mail berichten versturen.');
            }

            if (!$app['s_schema'])
            {
                throw new AccessDeniedHttpException('Je hebt onvoldoende
                    rechten om een E-mail bericht te versturen.');
            }

            if ($error_token = $app['form_token']->get_error())
            {
                $errors[] = $error_token;
            }

            if (!$user_mail_content)
            {
                $errors[] = 'Fout: leeg bericht. E-mail niet verzonden.';
            }

            $reply_ary = $app['mail_addr_user']->get($app['s_id'], $app['s_schema']);

            if (!count($reply_ary))
            {
                $errors[] = 'Fout: Je kan geen berichten naar andere gebruikers
                    verzenden als er geen E-mail adres is ingesteld voor je eigen account.';
            }

            if (count($errors))
            {
                $app['alert']->error($errors);
                $app['link']->redirect($app['r_users_show'], $app['pp_ary'],
                    ['id' => $id]);
            }

            $from_contacts = $app['db']->fetchAll('select c.value, tc.abbrev
                from ' . $app['s_schema'] . '.contact c, ' .
                    $app['s_schema'] . '.type_contact tc
                where c.flag_public >= ?
                    and c.id_user = ?
                    and c.id_type_contact = tc.id',
                    [cnst_access::TO_FLAG_PUBLIC[$user['accountrole']], $app['s_id']]);

            $from_user = $app['user_cache']->get($app['s_id'], $app['s_schema']);

            $vars = [
                'from_contacts'     => $from_contacts,
                'from_user'			=> $from_user,
                'from_schema'		=> $app['s_schema'],
                'to_user'			=> $user,
                'to_schema'			=> $app['tschema'],
                'is_same_system'	=> $app['s_system_self'],
                'msg_content'		=> $user_mail_content,
            ];

            $mail_template = $app['s_system_self']
                ? 'user_msg/msg'
                : 'user_msg/msg_intersystem';

            $app['queue.mail']->queue([
                'schema'	=> $app['tschema'],
                'to'		=> $app['mail_addr_user']->get($id, $app['tschema']),
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

            $app['link']->redirect($app['r_users_show'], $app['pp_ary'],
                ['id' => $id]);
        }

        $contacts = $app['db']->fetchAll('select c.*, tc.abbrev
            from ' . $app['tschema'] . '.contact c, ' .
                $app['tschema'] . '.type_contact tc
            where c.id_type_contact = tc.id
                and c.id_user = ?', [$id]);

        $count_messages = $app['db']->fetchColumn('select count(*)
            from ' . $app['tschema'] . '.messages
            where id_user = ?', [$id]);

        $count_transactions = $app['db']->fetchColumn('select count(*)
            from ' . $app['tschema'] . '.transactions
            where id_from = ?
                or id_to = ?', [$id, $id]);

        $sql_bind = [$user['letscode']];

        if ($status && isset($status_def_ary[$status]))
        {
            $and_status = isset($status_def_ary[$status]['sql'])
                ? ' and ' . $status_def_ary[$status]['sql']
                : '';

            if (isset($status_def_ary[$status]['sql_bind']))
            {
                $sql_bind[] = $status_def_ary[$status]['sql_bind'];
            }
        }
        else
        {
            $and_status = $app['s_admin'] ? '' : ' and u.status in (1, 2) ';
        }

        $next = $app['db']->fetchColumn('select id
            from ' . $app['tschema'] . '.users u
            where u.letscode > ?
            ' . $and_status . '
            order by u.letscode asc
            limit 1', $sql_bind);

        $prev = $app['db']->fetchColumn('select id
            from ' . $app['tschema'] . '.users u
            where u.letscode < ?
            ' . $and_status . '
            order by u.letscode desc
            limit 1', $sql_bind);

        $intersystem_missing = false;

        if ($app['s_admin']
            && $user['accountrole'] === 'interlets'
            && $app['intersystem_en'])
        {
            $intersystem_id = $app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.letsgroups
                where localletscode = ?', [$user['letscode']]);

            if (!$intersystem_id)
            {
                $intersystem_missing = true;
            }
        }
        else
        {
            $intersystem_id = false;
        }

        $app['assets']->add([
            'leaflet',
            'jqplot',
            'users_show.js',
            'plot_user_transactions.js',
        ]);

        if ($app['s_admin'] || $s_owner)
        {
            $app['assets']->add([
                'fileupload',
                'user_img.js',
            ]);
        }

        if ($app['s_admin'] || $s_owner)
        {
            $title = $app['s_admin'] ? 'Gebruiker' : 'Mijn gegevens';

            $app['btn_top']->edit($app['r_users_edit'], $app['pp_ary'],
                ['id' => $id], $title . ' aanpassen');

            if ($app['s_admin'])
            {
                $app['btn_top']->edit_pw('users_password_admin', $app['pp_ary'],
                ['id' => $id], 'Paswoord aanpassen');
            }
            else if ($s_owner)
            {
                $app['btn_top']->edit_pw('users_password', $app['pp_ary'],
                    [], 'Paswoord aanpassen');
            }
        }

        if ($app['s_admin'] && !$count_transactions && !$s_owner)
        {
            $app['btn_top']->del('users_del_admin', $app['pp_ary'],
                ['id' => $id], 'Gebruiker verwijderen');
        }

        if ($app['s_admin']
            || (!$s_owner && $user['status'] !== 7
                && !($app['s_guest'] && $app['s_system_self'])))
        {
            $tus = ['tuid' => $id];

            if (!$app['s_system_self'])
            {
                $tus['tus'] = $app['tschema'];
            }

            $app['btn_top']->add_trans('transactions_add', $app['s_ary'],
                $tus, 'Transactie naar ' . $app['account']->str($id, $app['tschema']));
        }

        $pp_status_ary = $app['pp_ary'];
        $pp_status_ary['status'] = $status;

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $app['btn_nav']->nav($app['r_users_show'], $pp_status_ary,
            $prev_ary, $next_ary, false);

        $app['btn_nav']->nav_list($app['r_users'], $pp_status_ary,
            [], 'Overzicht', 'users');

        $status_id = $user['status'];

        if (isset($user['adate']))
        {
            $status_id = ($app['new_user_treshold'] < strtotime($user['adate']) && $status_id == 1) ? 3 : $status_id;
        }

        $h_status_ary = cnst_status::LABEL_ARY;
        $h_status_ary[3] = 'Instapper';

        if ($s_owner && !$app['s_admin'])
        {
            $app['heading']->add('Mijn gegevens: ');
        }

        $app['heading']->add($app['account']->link($id, $app['pp_ary']));

        if ($status_id != 1)
        {
            $app['heading']->add(' <small><span class="text-');
            $app['heading']->add(cnst_status::CLASS_ARY[$status_id]);
            $app['heading']->add('">');
            $app['heading']->add($h_status_ary[$status_id]);
            $app['heading']->add('</span></small>');
        }

        if ($app['s_admin'])
        {
            if ($intersystem_missing)
            {
                $app['heading']->add(' <span class="label label-warning label-sm">');
                $app['heading']->add('<i class="fa fa-exclamation-triangle"></i> ');
                $app['heading']->add('De interSysteem-verbinding ontbreekt</span>');
            }
            else if ($intersystem_id)
            {
                $app['heading']->add(' ');
                $app['heading']->add($app['link']->link_fa('intersystem', $app['pp_ary'],
                    ['id' => $intersystem_id], 'Gekoppeld interSysteem',
                    ['class' => 'btn btn-default'], 'share-alt'));
            }
        }

        $app['heading']->fa('user');

        $out = '<div class="row">';
        $out .= '<div class="col-md-6">';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-body text-center ';
        $out .= 'center-block" id="img_user">';

        $show_img = $user['PictureFile'] ? true : false;

        $user_img = $show_img ? '' : ' style="display:none;"';
        $no_user_img = $show_img ? ' style="display:none;"' : '';

        $out .= '<img id="user_img"';
        $out .= $user_img;
        $out .= ' class="img-rounded img-responsive center-block" ';
        $out .= 'src="';

        if ($user['PictureFile'])
        {
            $out .= $app['s3_url'] . $user['PictureFile'];
        }
        else
        {
            $out .= $app['assets']->get('1.gif');
        }

        $out .= '" ';
        $out .= 'data-bucket-url="' . $app['s3_url'] . '"></img>';

        $out .= '<div id="no_user_img"';
        $out .= $no_user_img;
        $out .= '>';
        $out .= '<i class="fa fa-user fa-5x text-muted"></i>';
        $out .= '<br>Geen profielfoto</div>';

        $out .= '</div>';

        if ($app['s_admin'] || $s_owner)
        {
            $btn_del_attr = ['id'	=> 'btn_remove'];

            if (!$user['PictureFile'])
            {
                $btn_del_attr['style'] = 'display:none;';
            }

            $out .= '<div class="panel-footer">';
            $out .= '<span class="btn btn-success fileinput-button">';
            $out .= '<i class="fa fa-plus" id="img_plus"></i> Foto opladen';
            $out .= '<input id="fileupload" type="file" name="image" ';
            $out .= 'data-url="';

            if ($app['s_admin'])
            {
                $out .= $app['link']->context_path('users_image_upload_admin', $app['pp_ary'],
                    ['id' => $id]);
            }
            else
            {
                $out .= $app['link']->context_path('users_image_upload', $app['pp_ary'], []);
            }

            $out .= '" ';
            $out .= 'data-data-type="json" data-auto-upload="true" ';
            $out .= 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
            $out .= 'data-max-file-size="999000" data-image-max-width="400" ';
            $out .= 'data-image-crop="true" ';
            $out .= 'data-image-max-height="400"></span>&nbsp;';

            if ($app['s_admin'])
            {
                $out .= $app['link']->link_fa('users_image_del_admin', $app['pp_ary'],
                    ['id' => $id], 'Foto verwijderen',
                    array_merge($btn_del_attr, ['class' => 'btn btn-danger']),
                    'times');
            }
            else
            {
                $out .= $app['link']->link_fa('users_image_del', $app['pp_ary'],
                    [], 'Foto verwijderen',
                    array_merge($btn_del_attr, ['class' => 'btn btn-danger']),
                    'times');
            }

            $out .= '<p class="text-warning">';
            $out .= 'Je foto moet in het jpg/jpeg formaat zijn. ';
            $out .= 'Je kan ook een foto hierheen verslepen.</p>';
            $out .= '</div>';
        }

        $out .= '</div></div>';

        $out .= '<div class="col-md-6">';

        $out .= '<div class="panel panel-default printview">';
        $out .= '<div class="panel-heading">';
        $out .= '<dl>';

        $fullname_access = $user['fullname_access'] ?: 'admin';

        $out .= '<dt>';
        $out .= 'Volledige naam';
        $out .= '</dt>';

        if ($app['s_admin']
            || $s_owner
            || $app['item_access']->is_visible_xdb($fullname_access))
        {
            $out .= $this->get_dd($user['fullname'] ?? '');
        }
        else
        {
            $out .= '<dd>';
            $out .= '<span class="btn btn-default">';
            $out .= 'verborgen</span>';
            $out .= '</dd>';
        }

        if ($app['s_admin'])
        {
            $out .= '<dt>';
            $out .= 'Zichtbaarheid Volledige Naam';
            $out .= '</dt>';
            $out .= '<dd>';
            $out .= $app['item_access']->get_label_xdb($fullname_access);
            $out .= '</dd>';
        }

        $out .= '<dt>';
        $out .= 'Postcode';
        $out .= '</dt>';
        $out .= $this->get_dd($user['postcode'] ?? '');

        if ($app['s_admin'] || $s_owner)
        {
            $out .= '<dt>';
            $out .= 'Geboortedatum';
            $out .= '</dt>';

            if (isset($user['birthday']))
            {
                $out .= $app['date_format']->get($user['birthday'], 'day', $app['tschema']);
            }
            else
            {
                $out .= '<dd><i class="fa fa-times"></i></dd>';
            }
        }

        $out .= '<dt>';
        $out .= 'Hobbies / Interesses';
        $out .= '</dt>';
        $out .= $this->get_dd($user['hobbies'] ?? '');

        $out .= '<dt>';
        $out .= 'Commentaar';
        $out .= '</dt>';
        $out .= $this->get_dd($user['comments'] ?? '');

        if ($app['s_admin'])
        {
            $out .= '<dt>';
            $out .= 'Tijdstip aanmaak';
            $out .= '</dt>';

            if (isset($user['cdate']))
            {
                $out .= $this->get_dd($app['date_format']->get($user['cdate'], 'min', $app['tschema']));
            }
            else
            {
                $out .= '<dd><i class="fa fa-times"></i></dd>';
            }

            $out .= '<dt>';
            $out .= 'Tijdstip activering';
            $out .= '</dt>';

            if (isset($user['adate']))
            {
                $out .= $this->get_dd($app['date_format']->get($user['adate'], 'min', $app['tschema']));
            }
            else
            {
                $out .= '<dd><i class="fa fa-times"></i></dd>';
            }

            $out .= '<dt>';
            $out .= 'Laatste login';
            $out .= '</dt>';

            if (isset($user['lastlogin']))
            {
                $out .= $this->get_dd($app['date_format']->get($user['lastlogin'], 'min', $app['tschema']));
            }
            else
            {
                $out .= '<dd><i class="fa fa-times"></i></dd>';
            }

            $out .= '<dt>';
            $out .= 'Rechten / rol';
            $out .= '</dt>';
            $out .= $this->get_dd(cnst_role::LABEL_ARY[$user['accountrole']]);

            $out .= '<dt>';
            $out .= 'Status';
            $out .= '</dt>';
            $out .= $this->get_dd(cnst_status::LABEL_ARY[$user['status']]);

            $out .= '<dt>';
            $out .= 'Commentaar van de admin';
            $out .= '</dt>';
            $out .= $this->get_dd($user['admincomment'] ?? '');
        }

        $out .= '<dt>Saldo</dt>';
        $out .= '<dd>';
        $out .= '<span class="label label-info">';
        $out .= $user['saldo'];
        $out .= '</span>&nbsp;';
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= '</dd>';

        if ($user['minlimit'] !== '')
        {
            $out .= '<dt>Minimum limiet</dt>';
            $out .= '<dd>';
            $out .= '<span class="label label-danger">';
            $out .= $user['minlimit'];
            $out .= '</span>&nbsp;';
            $out .= $app['config']->get('currency', $app['tschema']);
            $out .= '</dd>';
        }

        if ($user['maxlimit'] !== '')
        {
            $out .= '<dt>Maximum limiet</dt>';
            $out .= '<dd>';
            $out .= '<span class="label label-success">';
            $out .= $user['maxlimit'];
            $out .= '</span>&nbsp;';
            $out .= $app['config']->get('currency', $app['tschema']);
            $out .= '</dd>';
        }

        if ($app['s_admin'] || $s_owner)
        {
            $out .= '<dt>';
            $out .= 'Periodieke Overzichts E-mail';
            $out .= '</dt>';
            $out .= $user['cron_saldo'] ? 'Aan' : 'Uit';
            $out .= '</dl>';
        }

        $out .= '</div></div></div></div>';

        // response form

        $user_mail_disabled = true;

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
        else
        {
            $placeholder = '';
            $user_mail_disabled = false;
        }

        $out .= '<h3><i class="fa fa-envelop-o"></i> ';
        $out .= 'Stuur een bericht naar ';
        $out .=  $app['account']->link($id, $app['pp_ary']);
        $out .= '</h3>';
        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post"">';

        $out .= '<div class="form-group">';
        $out .= '<textarea name="user_mail_content" rows="6" placeholder="';
        $out .= $placeholder . '" ';
        $out .= 'class="form-control" required';
        $out .= $user_mail_disabled ? ' disabled' : '';
        $out .= '>';
        $out .= $user_mail_content;
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

        $out .= $app['form_token']->get_hidden_input();
        $out .= '<input type="submit" name="user_mail_submit" ';
        $out .= 'value="Versturen" class="btn btn-default"';
        $out .= $user_mail_disabled ? ' disabled' : '';
        $out .= '>';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        // Contacts

		$out .= '<div class="row">';
		$out .= '<div class="col-md-12">';

		$out .= '<h3>';
		$out .= '<i class="fa fa-map-marker"></i>';
		$out .= ' Contactinfo van ';
		$out .= $app['account']->link($id, $app['pp_ary']);
        $out .= ' ';

        if ($app['s_admin'])
        {
            $out .= $app['link']->link('users_contacts_add_admin', $app['pp_ary'],
                ['user_id' => $id], 'Toevoegen', [
                'class'	=> 'btn btn-success',
                'title'	=> $title,
            ], 'plus');
        }
        else if ($s_owner)
        {
            $out .= $app['link']->link('users_contacts_add', $app['pp_ary'],
                [], 'Toevoegen', [
                    'class'	=> 'btn btn-success',
                    'title'	=> $title,
                ], 'plus');
        }

		$out .= '</h3>';

        if (count($contacts))
        {
            $out .= '<div class="panel panel-danger">';
            $out .= '<div class="table-responsive">';
            $out .= '<table class="table table-hover ';
            $out .= 'table-striped table-bordered footable" ';
            $out .= 'data-sort="false">';

            $out .= '<thead>';
            $out .= '<tr>';

            $out .= '<th>Type</th>';
            $out .= '<th>Waarde</th>';
            $out .= '<th data-hide="phone, tablet">Commentaar</th>';

            if ($app['s_admin'] || $s_owner)
            {
                $out .= '<th data-hide="phone, tablet">Zichtbaarheid</th>';
                $out .= '<th data-sort-ignore="true" ';
                $out .= 'data-hide="phone, tablet">Verwijderen</th>';
            }

            $out .= '</tr>';
            $out .= '</thead>';

            $out .= '<tbody>';

            foreach ($contacts as $c)
            {
                $tr = [];

                $tr[] = $c['abbrev'];

                if (!$app['item_access']->is_visible_flag_public($c['flag_public']) && !$s_owner)
                {
                    $tr_c = '<span class="btn btn-default">verborgen</span>';
                    $tr[] = $tr_c;
                    $tr[] = $tr_c;
                }
                else if ($s_owner || $app['s_admin'])
                {
                    $tr_c = $app['link']->link_no_attr('users_contacts_edit', $app['pp_ary'],
                        ['contact_id' => $c['id'], 'user_id' => $id], $c['value']);

                    if ($c['abbrev'] == 'adr')
                    {
                        $app['distance']->set_to_geo($c['value']);

                        if (!$app['s_elas_guest'] && !$app['s_master'])
                        {
                            $tr_c .= $app['distance']->set_from_geo($app['s_id'], $app['s_schema'])
                                ->calc()
                                ->format_parenthesis();
                        }
                    }

                    $tr[] = $tr_c;

                    if (isset($c['comments']))
                    {
                        $tr[] = $app['link']->link_no_attr('users_contacts_edit', $app['pp_ary'],
                            ['contact_id' => $c['id'], 'user_id' => $id], $c['comments']);
                    }
                    else
                    {
                        $tr[] = '&nbsp;';
                    }
                }
                else if ($c['abbrev'] === 'mail')
                {
                    $tr[] = '<a href="mailto:' . $c['value'] . '">' .
                        $c['value'] . '</a>';

                    $tr[] = htmlspecialchars($c['comments'], ENT_QUOTES);
                }
                else if ($c['abbrev'] === 'web')
                {
                    $tr[] = '<a href="' . $c['value'] . '">' .
                        $c['value'] .  '</a>';

                    $tr[] = htmlspecialchars($c['comments'], ENT_QUOTES);
                }
                else
                {
                    $tr_c = htmlspecialchars($c['value'], ENT_QUOTES);

                    if ($c['abbrev'] == 'adr')
                    {
                        $app['distance']->set_to_geo($c['value']);

                        if (!$app['s_elas_guest'] && !$app['s_master'])
                        {
                            $tr_c .= $app['distance']->set_from_geo($app['s_id'], $app['s_schema'])
                                ->calc()
                                ->format_parenthesis();
                        }
                    }

                    $tr[] = $tr_c;

                    $tr[] = htmlspecialchars($c['comments'], ENT_QUOTES);
                }

                if ($app['s_admin'] || $s_owner)
                {
                    $tr[] = $app['item_access']->get_label_flag_public($c['flag_public']);

                    $tr[] = $app['link']->link_fa('users_contacts_del', $app['pp_ary'],
                        ['contact_id' => $c['id'], 'user_id' => $id], 'Verwijderen',
                        ['class' => 'btn btn-danger'], 'times');
                }

                $out .= '<tr><td>';
                $out .= implode('</td><td>', $tr);
                $out .= '</td></tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';

            if ($app['distance']->has_to_data())
            {
                $out .= '<div class="panel-footer">';
                $out .= '<div class="user_map" id="map" data-markers="';
                $out .= $app['distance']->get_to_data();
                $out .= '" ';
                $out .= 'data-token="';
                $out .= $app['mapbox_token'];
                $out .= '"></div>';
                $out .= '</div>';
            }
        }
        else
        {
            $out .= '<div class="panel panel-danger">';
            $out .= '<div class="panel-body">';
            $out .= '<p>Er is geen contactinfo voor ';
            $out .= $app['account']->str($id, $app['tschema']);
            $out .= '.</p>';
        }

        $out .= '</div></div>';
        $out .= '</div></div>';

        //

        $out .= '<div class="row">';
        $out .= '<div class="col-md-12">';

        $out .= '<h3>Saldo: <span class="label label-info">';
        $out .= $user['saldo'];
        $out .= '</span> ';
        $out .= $app['config']->get('currency', $app['tschema']);
        $out .= '</h3>';
        $out .= '</div></div>';

        $out .= '<div class="row print-hide">';
        $out .= '<div class="col-md-6">';
        $out .= '<div id="chartdiv" data-height="480px" data-width="960px" ';

        $out .= 'data-plot-user-transactions="';
        $out .= htmlspecialchars($app['link']->context_path('plot_user_transactions',
            $app['pp_ary'], ['user_id' => $id, 'days' => $tdays]));
        $out .= '" ';

        $out .= 'data-transactions-show="';
        $out .= htmlspecialchars($app['link']->context_path('transactions_show',
            $app['pp_ary'], ['id' => $id]));
        $out .= '" ';

        $out .= 'data-users-show="';
        $out .= htmlspecialchars($app['link']->context_path($app['r_users_show'],
            $app['pp_ary'], ['id' => $id]));
        $out .= '" ';

        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-md-6">';
        $out .= '<div id="donutdiv" data-height="480px" ';
        $out .= 'data-width="960px"></div>';
        $out .= '<h4>Interacties laatste jaar</h4>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="row">';
        $out .= '<div class="col-md-6">';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-body">';

        $account_str = $app['account']->str($id, $app['tschema']);

        $attr_link_messages = $attr_link_transactions = [
            'class'     => 'btn btn-default btn-lg btn-block',
            'disabled'  => 'disabled',
        ];

        if ($count_messages)
        {
            unset($attr_link_messages['disabled']);
        }

        if ($count_transactions)
        {
            unset($attr_link_transactions['disabled']);
        }


        $out .= $app['link']->link_fa($app['r_messages'],
            $app['pp_ary'],
            ['f' => ['uid' => $id]],
            'Vraag en aanbod van ' . $account_str .
            ' (' . $count_messages . ')',
            $attr_link_messages,
            'exchange');

        $out .= $app['link']->link_fa('transactions',
            $app['pp_ary'],
            ['f' => ['uid' => $id]],
            'Transacties van ' . $account_str .
            ' (' . $count_transactions . ')',
            $attr_link_transactions,
            'exchange');

        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get();
    }

    private function get_dd(string $str):string
    {
        $out =  '<dd>';
        $out .=  $str ? htmlspecialchars($str, ENT_QUOTES) : '<span class="fa fa-times"></span>';
        $out .=  '</dd>';
        return $out;
    }
}
