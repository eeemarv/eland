<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class users_show
{
    public function active_status(Request $request, app $app, string $status, int $id):Response
    {
        return $this->status($request, $app, $status, $id);
    }

    public function all_status(Request $request, app $app, string $status, int $id):Response
    {
        $s_owner = !$app['s_guest']
            && $app['s_system_self']
            && $app['s_id'] == $id
            && $id;

        $user_mail_cc = $request->isMethod('POST') ? $user_mail_cc : 1;

        $user = $app['user_cache']->get($id, $app['tschema']);

        if (!$app['s_admin'] && !in_array($user['status'], [1, 2]))
        {
            $app['alert']->error('Je hebt geen toegang tot deze gebruiker.');
            $app['link']->redirect($app['r_users'], $app['pp_ary'], []);
        }

        $mail_to = $app['mail_addr_user']->get($user['id'], $app['tschema']);
        $mail_from = $app['s_schema']
            && !$app['s_master']
            && !$app['s_elas_guest']
                ? $app['mail_addr_user']->get($app['s_id'], $app['s_schema'])
                : [];

        // process mail form




        if ($app['s_admin'])
        {
            $count_transactions = $app['db']->fetchColumn('select count(*)
                from ' . $app['tschema'] . '.transactions
                where id_from = ?
                    or id_to = ?', [$id, $id]);
        }

        $sql_bind = [$user['letscode']];

        if ($link && isset($st[$link]))
        {
            $and_status = isset($st[$link]['sql'])
                ? ' and ' . $st[$link]['sql']
                : '';

            if (isset($st[$link]['sql_bind']))
            {
                $sql_bind[] = $st[$link]['sql_bind'];
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
            'user.js',
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

            $app['btn_top']->edit('users', $app['pp_ary'],
                ['edit' => $id], $title . ' aanpassen');

            $app['btn_top']->edit_pw('users', $app['pp_ary'],
                ['pw' => $id], 'Paswoord aanpassen');
        }

        if ($app['s_admin'] && !$count_transactions && !$s_owner)
        {
            $app['btn_top']->del('users', $app['pp_ary'],
                ['del' => $id], 'Gebruiker verwijderen');
        }

        if ($app['s_admin']
            || (!$s_owner && $user['status'] !== 7
                && !($app['s_guest'] && $app['s_system_self'])))
        {
            $tus = ['add' => 1, 'tuid' => $id];

            if (!$app['s_system_self'])
            {
                $tus['tus'] = $app['tschema'];
            }

            $app['btn_top']->add_trans('transactions', $app['s_ary'],
                $tus, 'Transactie naar ' . $app['account']->str($id, $app['tschema']));
        }

        $link_ary = $link ? ['link' => $link] : [];
        $prev_ary = $prev ? array_merge($link_ary, ['id' => $prev]) : [];
        $next_ary = $next ? array_merge($link_ary, ['id' => $next]) : [];

        $app['btn_nav']->nav('users', $app['pp_ary'],
            $prev_ary, $next_ary, false);

        $app['btn_nav']->nav_list('users', $app['pp_ary'],
            ['link' => $link], 'Overzicht', 'users');

        $status = $user['status'];
        $status = ($app['new_user_treshold'] < strtotime($user['adate']) && $status == 1) ? 3 : $status;

        $h_status_ary = cnst_status::LABEL_ARY;
        $h_status_ary[3] = 'Instapper';

        if ($s_owner && !$app['s_admin'])
        {
            $app['heading']->add('Mijn gegevens: ');
        }

        $app['heading']->add($app['account']->link($id, $app['pp_ary']));

        if ($status != 1)
        {
            $app['heading']->add(' <small><span class="text-');
            $app['heading']->add(cnst_status::CLASS_ARY[$status]);
            $app['heading']->add('">');
            $app['heading']->add($h_status_ary[$status]);
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
            $out .= $app['rootpath'] . 'gfx/1.gif';
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
            $attr = ['id'	=> 'btn_remove'];

            if (!$user['PictureFile'])
            {
                $attr['style'] = 'display:none;';
            }

            $out .= '<div class="panel-footer">';
            $out .= '<span class="btn btn-success fileinput-button">';
            $out .= '<i class="fa fa-plus" id="img_plus"></i> Foto opladen';
            $out .= '<input id="fileupload" type="file" name="image" ';
            $out .= 'data-url="';

            $out .= $app['link']->context_path('users', $app['pp_ary'],
                ['img' => 1, 'id' => $id]);

            $out .= '" ';
            $out .= 'data-data-type="json" data-auto-upload="true" ';
            $out .= 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
            $out .= 'data-max-file-size="999000" data-image-max-width="400" ';
            $out .= 'data-image-crop="true" ';
            $out .= 'data-image-max-height="400"></span>&nbsp;';

            $out .= $app['link']->link_fa('users', $app['pp_ary'],
                ['img_del' => 1, 'id' => $id],
                'Foto verwijderen',
                array_merge($attr, ['class' => 'btn btn-danger']),
                'times');

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
            $out .= get_dd($user['fullname'] ?? '');
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
        $out .= get_dd($user['postcode'] ?? '');

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
        $out .= get_dd($user['hobbies'] ?? '');

        $out .= '<dt>';
        $out .= 'Commentaar';
        $out .= '</dt>';
        $out .= get_dd($user['comments'] ?? '');

        if ($app['s_admin'])
        {
            $out .= '<dt>';
            $out .= 'Tijdstip aanmaak';
            $out .= '</dt>';

            if (isset($user['cdate']))
            {
                $out .= get_dd($app['date_format']->get($user['cdate'], 'min', $app['tschema']));
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
                $out .= get_dd($app['date_format']->get($user['adate'], 'min', $app['tschema']));
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
                $out .= get_dd($app['date_format']->get($user['lastlogin'], 'min', $app['tschema']));
            }
            else
            {
                $out .= '<dd><i class="fa fa-times"></i></dd>';
            }

            $out .= '<dt>';
            $out .= 'Rechten / rol';
            $out .= '</dt>';
            $out .= get_dd(cnst_role::LABEL_ARY[$user['accountrole']]);

            $out .= '<dt>';
            $out .= 'Status';
            $out .= '</dt>';
            $out .= get_dd(cnst_status::LABEL_ARY[$user['status']]);

            $out .= '<dt>';
            $out .= 'Commentaar van de admin';
            $out .= '</dt>';
            $out .= get_dd($user['admincomment'] ?? '');
        }

        $out .= '<dt>Saldo</dt>';
        $out .= '<dd>';
        $out .= '<span class="label label-info">';
        $out .= $user['saldo'];
        echo'</span>&nbsp;';
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

        $out .= '<div id="contacts" ';
        $out .= 'data-url="';
        $out .= $app->path('contacts', array_merge(['pp_ary'], [
            'inline'	=> '1',
            'uid'		=> $message['id_user'],
        ]));
        $out .= '"></div>';

        // response form

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
        }

        $disabled = !$app['s_schema']
            || !count($mail_to)
            || !count($mail_from)
            || $s_owner;

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
        $out .= '</div>';

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
            $app['pp_ary'], ['id' => 1]));
        $out .= '" ';

        $out .= 'data-users-show="';
        $out .= htmlspecialchars($app['link']->context_path($app['r_users_show'],
            $app['pp_ary'], ['id' => 1]));
        $out .= '" ';

        $out .= '"></div>';
        $out .= '</div>';
        $out .= '<div class="col-md-6">';
        $out .= '<div id="donutdiv" data-height="480px" ';
        $out .= 'data-width="960px"></div>';
        $out .= '<h4>Interacties laatste jaar</h4>';
        $out .= '</div>';
        $out .= '</div>';

        if ($user['status'] == 1 || $user['status'] == 2)
        {
            $out .= '<div id="messages" ';
            $out .= 'data-url="';
            $out .= $app->path('messages', array_merge($app['pp_ary'], [
                'inline'	=> '1',
                'f'			=> [
                    'uid'	=> $id,
                ],
            ]));
            $out .= '" class="print-hide"></div>';
        }

        $out .= '<div id="transactions" ';
        $out .= 'data-url="';

        $out .= $app->path('transactions', array_merge($app['pp_ary'], [
            'inline'	=> '1',
            'f'			=> [
                'uid'	=> $id,
            ],
        ]));

        $out .= '" class="print-hide"></div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get($request);
    }
}
