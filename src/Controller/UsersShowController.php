<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use app\cnst\statuscnst;
use App\Cnst\AccessCnst;
use app\cnst\rolecnst;
use controller\users_list;
use Doctrine\DBAL\Connection as Db;

class UsersShowController extends AbstractController
{
    public function users_show(
        Request $request,
        app $app,
        string $status,
        int $id,
        Db $db
    ):Response
    {
        return $this->users_show_admin($request, $app, $status, $id, $db);
    }

    public function users_show_admin(
        Request $request,
        app $app,
        string $status,
        int $id,
        Db $db
    ):Response
    {
        $tdays = $request->query->get('tdays', '365');

        $user_mail_content = $request->request->get('user_mail_content', '');
        $user_mail_cc = $request->request->get('user_mail_cc', '') ? true : false;
        $user_mail_submit = $request->request->get('user_mail_submit', '') ? true : false;

        $user_mail_cc = $request->isMethod('POST') ? $user_mail_cc : true;

        $s_owner = !$app['pp_guest']
            && $app['s_system_self']
            && $app['s_id'] === $id
            && $id;

        $user = $app['user_cache']->get($id, $app['pp_schema']);

        if (!$user)
        {
            throw new NotFoundHttpException(
                'De gebruiker met id ' . $id . ' bestaat niet');
        }

        if (!$app['pp_admin'] && !in_array($user['status'], [1, 2]))
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot deze gebruiker.');
        }

        $status_def_ary = users_list::get_status_def_ary($app['pp_admin'], $app['new_user_treshold']);

        // process mail form

        if ($request->isMethod('POST') && $user_mail_submit)
        {
            $errors = [];

            if ($app['s_master'])
            {
                throw new AccessDeniedHttpException('Het master account kan
                    geen E-mail berichten versturen.');
            }

            if (!$app['s_schema'] || $app['s_elas_guest'])
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

            if (!count($errors))
            {
                $from_contacts = $db->fetchAll('select c.value, tc.abbrev
                    from ' . $app['s_schema'] . '.contact c, ' .
                        $app['s_schema'] . '.type_contact tc
                    where c.flag_public >= ?
                        and c.id_user = ?
                        and c.id_type_contact = tc.id',
                        [AccessCnst::TO_FLAG_PUBLIC[$user['accountrole']], $app['s_id']]);

                $from_user = $app['user_cache']->get($app['s_id'], $app['s_schema']);

                $vars = [
                    'from_contacts'     => $from_contacts,
                    'from_user'			=> $from_user,
                    'from_schema'		=> $app['s_schema'],
                    'to_user'			=> $user,
                    'to_schema'			=> $app['pp_schema'],
                    'is_same_system'	=> $app['s_system_self'],
                    'msg_content'		=> $user_mail_content,
                ];

                $mail_template = $app['s_system_self']
                    ? 'user_msg/msg'
                    : 'user_msg/msg_intersystem';

                $app['queue.mail']->queue([
                    'schema'	=> $app['pp_schema'],
                    'to'		=> $app['mail_addr_user']->get($id, $app['pp_schema']),
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
                        'schema'	=> $app['pp_schema'],
                        'to' 		=> $app['mail_addr_user']->get($app['s_id'], $app['s_schema']),
                        'template' 	=> $mail_template,
                        'vars'		=> $vars,
                    ], 8000);
                }

                $app['alert']->success('E-mail bericht verzonden.');

                $app['link']->redirect($app['r_users_show'], $app['pp_ary'],
                    ['id' => $id]);

            }

            $app['alert']->error($errors);
        }

        $count_messages = $db->fetchColumn('select count(*)
            from ' . $app['pp_schema'] . '.messages
            where id_user = ?', [$id]);

        $count_transactions = $db->fetchColumn('select count(*)
            from ' . $app['pp_schema'] . '.transactions
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
            $and_status = $app['pp_admin'] ? '' : ' and u.status in (1, 2) ';
        }

        $next = $db->fetchColumn('select id
            from ' . $app['pp_schema'] . '.users u
            where u.letscode > ?
            ' . $and_status . '
            order by u.letscode asc
            limit 1', $sql_bind);

        $prev = $db->fetchColumn('select id
            from ' . $app['pp_schema'] . '.users u
            where u.letscode < ?
            ' . $and_status . '
            order by u.letscode desc
            limit 1', $sql_bind);

        $intersystem_missing = false;

        if ($app['pp_admin']
            && $user['accountrole'] === 'interlets'
            && $app['intersystem_en'])
        {
            $intersystem_id = $db->fetchColumn('select id
                from ' . $app['pp_schema'] . '.letsgroups
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

        $contacts_user_show_inline = new contacts_user_show_inline();
        $contacts_response = $contacts_user_show_inline->contacts_user_show_inline($app, $user['id']);
        $contacts_content = $contacts_response->getContent();

        $app['assets']->add([
            'jqplot',
            'plot_user_transactions.js',
        ]);

        if ($app['pp_admin'] || $s_owner)
        {
            $app['assets']->add([
                'fileupload',
                'upload_image.js',
            ]);
        }

        if ($app['pp_admin'] || $s_owner)
        {
            $title = $app['pp_admin'] ? 'Gebruiker' : 'Mijn gegevens';

            $app['btn_top']->edit($app['r_users_edit'], $app['pp_ary'],
                ['id' => $id], $title . ' aanpassen');

            if ($app['pp_admin'])
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

        if ($app['pp_admin'] && !$count_transactions && !$s_owner)
        {
            $app['btn_top']->del('users_del_admin', $app['pp_ary'],
                ['id' => $id], 'Gebruiker verwijderen');
        }

        if ($app['pp_admin']
            || (!$s_owner && $user['status'] !== 7
                && !($app['pp_guest'] && $app['s_system_self'])))
        {
            $tus = ['tuid' => $id];

            if (!$app['s_system_self'])
            {
                $tus['tus'] = $app['pp_schema'];
            }

            $app['btn_top']->add_trans('transactions_add', $app['s_ary'],
                $tus, 'Transactie naar ' . $app['account']->str($id, $app['pp_schema']));
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

        $h_status_ary = statuscnst::LABEL_ARY;
        $h_status_ary[3] = 'Instapper';

        if ($s_owner && !$app['pp_admin'])
        {
            $app['heading']->add('Mijn gegevens: ');
        }

        $app['heading']->add_raw($app['account']->link($id, $app['pp_ary']));

        if ($status_id != 1)
        {
            $app['heading']->add_raw(' <small><span class="text-');
            $app['heading']->add_raw(statuscnst::CLASS_ARY[$status_id]);
            $app['heading']->add_raw('">');
            $app['heading']->add_raw($h_status_ary[$status_id]);
            $app['heading']->add_raw('</span></small>');
        }

        if ($app['pp_admin'])
        {
            if ($intersystem_missing)
            {
                $app['heading']->add_raw(' <span class="label label-warning label-sm">');
                $app['heading']->add_raw('<i class="fa fa-exclamation-triangle"></i> ');
                $app['heading']->add_raw('De interSysteem-verbinding ontbreekt</span>');
            }
            else if ($intersystem_id)
            {
                $app['heading']->add(' ');
                $app['heading']->add_raw($app['link']->link_fa('intersystems_show', $app['pp_ary'],
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

        $out .= '<img id="img"';
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
        $out .= 'data-base-url="' . $app['s3_url'] . '">';

        $out .= '<div id="no_img"';
        $out .= $no_user_img;
        $out .= '>';
        $out .= '<i class="fa fa-user fa-5x text-muted"></i>';
        $out .= '<br>Geen profielfoto</div>';

        $out .= '</div>';

        if ($app['pp_admin'] || $s_owner)
        {
            $btn_del_attr = ['id'	=> 'btn_remove'];

            if (!$user['PictureFile'])
            {
                $btn_del_attr['style'] = 'display:none;';
            }

            $out .= '<div class="panel-footer">';
            $out .= '<span class="btn btn-success btn-lg btn-block fileinput-button">';
            $out .= '<i class="fa fa-plus" id="img_plus"></i> Foto opladen';
            $out .= '<input id="fileupload" type="file" name="image" ';
            $out .= 'data-url="';

            if ($app['pp_admin'])
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
            $out .= 'data-accept-file-types="/(\.|\/)(jpe?g|png|gif)$/i" ';
            $out .= 'data-max-file-size="999000" data-image-max-width="400" ';
            $out .= 'data-image-crop="true" ';
            $out .= 'data-image-max-height="400"></span>';

            $out .= '<p class="text-warning">';
            $out .= 'Toegestane formaten: jpg/jpeg, png, gif. ';
            $out .= 'Je kan ook een foto hierheen verslepen.</p>';

            if ($app['pp_admin'])
            {
                $out .= $app['link']->link_fa('users_image_del_admin', $app['pp_ary'],
                    ['id' => $id], 'Foto verwijderen',
                    array_merge($btn_del_attr, ['class' => 'btn btn-danger btn-lg btn-block']),
                    'times');
            }
            else
            {
                $out .= $app['link']->link_fa('users_image_del', $app['pp_ary'],
                    [], 'Foto verwijderen',
                    array_merge($btn_del_attr, ['class' => 'btn btn-danger btn-lg btn-block']),
                    'times');
            }

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

        if ($app['pp_admin']
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

        if ($app['pp_admin'])
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

        if ($app['pp_admin'] || $s_owner)
        {
            $out .= '<dt>';
            $out .= 'Geboortedatum';
            $out .= '</dt>';

            if (isset($user['birthday']))
            {
                $out .= $app['date_format']->get($user['birthday'], 'day', $app['pp_schema']);
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

        if ($app['pp_admin'])
        {
            $out .= '<dt>';
            $out .= 'Tijdstip aanmaak';
            $out .= '</dt>';

            if (isset($user['cdate']))
            {
                $out .= $this->get_dd($app['date_format']->get($user['cdate'], 'min', $app['pp_schema']));
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
                $out .= $this->get_dd($app['date_format']->get($user['adate'], 'min', $app['pp_schema']));
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
                $out .= $this->get_dd($app['date_format']->get($user['lastlogin'], 'min', $app['pp_schema']));
            }
            else
            {
                $out .= '<dd><i class="fa fa-times"></i></dd>';
            }

            $out .= '<dt>';
            $out .= 'Rechten / rol';
            $out .= '</dt>';
            $out .= $this->get_dd(rolecnst::LABEL_ARY[$user['accountrole']]);

            $out .= '<dt>';
            $out .= 'Status';
            $out .= '</dt>';
            $out .= $this->get_dd(statuscnst::LABEL_ARY[$user['status']]);

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
        $out .= $app['config']->get('currency', $app['pp_schema']);
        $out .= '</dd>';

        if ($user['minlimit'] !== '')
        {
            $out .= '<dt>Minimum limiet</dt>';
            $out .= '<dd>';
            $out .= '<span class="label label-danger">';
            $out .= $user['minlimit'];
            $out .= '</span>&nbsp;';
            $out .= $app['config']->get('currency', $app['pp_schema']);
            $out .= '</dd>';
        }

        if ($user['maxlimit'] !== '')
        {
            $out .= '<dt>Maximum limiet</dt>';
            $out .= '<dd>';
            $out .= '<span class="label label-success">';
            $out .= $user['maxlimit'];
            $out .= '</span>&nbsp;';
            $out .= $app['config']->get('currency', $app['pp_schema']);
            $out .= '</dd>';
        }

        if ($app['pp_admin'] || $s_owner)
        {
            $out .= '<dt>';
            $out .= 'Periodieke Overzichts E-mail';
            $out .= '</dt>';
            $out .= $user['cron_saldo'] ? 'Aan' : 'Uit';
            $out .= '</dl>';
        }

        $out .= '</div></div></div></div>';

        $out .= self::get_mail_form($app, $id, $user_mail_content, $user_mail_cc);

        $out .= $contacts_content;

        $out .= '<div class="row">';
        $out .= '<div class="col-md-12">';

        $out .= '<h3>Huidig saldo: <span class="label label-info">';
        $out .= $user['saldo'];
        $out .= '</span> ';
        $out .= $app['config']->get('currency', $app['pp_schema']);
        $out .= '</h3>';
        $out .= '</div></div>';

        $out .= '<div class="row print-hide">';
        $out .= '<div class="col-md-6">';
        $out .= '<div id="chartdiv" data-height="480px" data-width="960px" ';

        $out .= 'data-plot-user-transactions="';
        $out .= htmlspecialchars($app['link']->context_path('plot_user_transactions',
            $app['pp_ary'], ['user_id' => $id, 'days' => $tdays]));

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
        $out .= '<div class="col-md-12">';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-body">';

        $account_str = $app['account']->str($id, $app['pp_schema']);

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
            'newspaper-o');

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

        $app['menu']->set('users');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }

    private function get_dd(string $str):string
    {
        $out =  '<dd>';
        $out .=  $str ? htmlspecialchars($str, ENT_QUOTES) : '<span class="fa fa-times"></span>';
        $out .=  '</dd>';
        return $out;
    }

    public static function get_mail_form(
        app $app,
        int $user_id,
        string $user_mail_content,
        bool $user_mail_cc
    ):string
    {
        $s_owner = !$app['pp_guest']
            && $app['s_system_self']
            && $app['s_id'] === $user_id
            && $user_id;

        $mail_from = $app['s_schema']
            && !$app['s_master']
            && !$app['s_elas_guest']
                ? $app['mail_addr_user']->get($app['s_id'], $app['s_schema'])
                : [];

        $mail_to = $app['mail_addr_user']->get($user_id, $app['pp_schema']);

        $user_mail_disabled = true;

        if ($app['s_elas_guest'])
        {
            $placeholder = 'Als eLAS gast kan je niet het E-mail formulier gebruiken.';
        }
        else if ($app['s_master'])
        {
            $placeholder = 'Het master account kan geen berichten versturen.';
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
        $out .= $user_mail_disabled ? ' disabled' : '';
        $out .= '> Stuur een kopie naar mijzelf';
        $out .= '</label>';
        $out .= '</div>';

        $out .= $app['form_token']->get_hidden_input();
        $out .= '<input type="submit" name="user_mail_submit" ';
        $out .= 'value="Versturen" class="btn btn-info btn-lg"';
        $out .= $user_mail_disabled ? ' disabled' : '';
        $out .= '>';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $out;
    }
}