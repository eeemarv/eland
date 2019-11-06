<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Controller\UsersListController;
use App\Cnst\StatusCnst;
use App\Cnst\AccessCnst;
use App\Cnst\RoleCnst;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\DistanceService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;

class UsersShowAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $status,
        int $id,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        DateFormatService $date_format_service,
        UserCacheService $user_cache_service,
        DistanceService $distance_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service,
        ContactsUserShowInlineController $contacts_user_show_inline_controller,
        string $env_s3_url,
        string $env_mapbox_token
    ):Response
    {
        $errors = [];

        $tdays = $request->query->get('tdays', '365');

        $user_mail_content = $request->request->get('user_mail_content', '');
        $user_mail_cc = $request->request->get('user_mail_cc', '') ? true : false;
        $user_mail_submit = $request->request->get('user_mail_submit', '') ? true : false;

        $user_mail_cc = $request->isMethod('POST') ? $user_mail_cc : true;

        $s_owner = !$pp->is_guest()
            && $su->is_system_self()
            && $su->id() === $id
            && $id;

        $user = $user_cache_service->get($id, $pp->schema());

        if (!$user)
        {
            throw new NotFoundHttpException(
                'De gebruiker met id ' . $id . ' bestaat niet');
        }

        if (!$pp->is_admin() && !in_array($user['status'], [1, 2]))
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot deze gebruiker.');
        }

        $status_def_ary = UsersListController::get_status_def_ary($config_service, $pp);

        // process mail form

        if ($request->isMethod('POST') && $user_mail_submit)
        {
            if ($su->is_master())
            {
                throw new AccessDeniedHttpException('Het master account kan
                    geen E-mail berichten versturen.');
            }

            if (!$su->schema() || $su->is_elas_guest())
            {
                throw new AccessDeniedHttpException('Je hebt onvoldoende
                    rechten om een E-mail bericht te versturen.');
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!$user_mail_content)
            {
                $errors[] = 'Fout: leeg bericht. E-mail niet verzonden.';
            }

            $reply_ary = $mail_addr_user_service->get($su->id(), $su->schema());

            if (!count($reply_ary))
            {
                $errors[] = 'Fout: Je kan geen berichten naar andere gebruikers
                    verzenden als er geen E-mail adres is ingesteld voor je eigen account.';
            }

            if (!count($errors))
            {
                $from_contacts = $db->fetchAll('select c.value, tc.abbrev
                    from ' . $su->schema() . '.contact c, ' .
                        $su->schema() . '.type_contact tc
                    where c.flag_public >= ?
                        and c.id_user = ?
                        and c.id_type_contact = tc.id',
                        [AccessCnst::TO_FLAG_PUBLIC[$user['accountrole']], $su->id()]);

                $from_user = $user_cache_service->get($su->id(), $su->schema());

                $vars = [
                    'from_contacts'     => $from_contacts,
                    'from_user'			=> $from_user,
                    'from_schema'		=> $su->schema(),
                    'to_user'			=> $user,
                    'to_schema'			=> $pp->schema(),
                    'is_same_system'	=> $su->is_system_self(),
                    'msg_content'		=> $user_mail_content,
                ];

                $mail_template = $su->is_system_self()
                    ? 'user_msg/msg'
                    : 'user_msg/msg_intersystem';

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to'		=> $mail_addr_user_service->get($id, $pp->schema()),
                    'reply_to'	=> $reply_ary,
                    'template'	=> $mail_template,
                    'vars'		=> $vars,
                ], 8000);

                if ($user_mail_cc)
                {
                    $mail_template = $su->is_system_self()
                        ? 'user_msg/copy'
                        : 'user_msg/copy_intersystem';

                    $mail_queue->queue([
                        'schema'	=> $pp->schema(),
                        'to' 		=> $mail_addr_user_service->get($su->id(), $su->schema()),
                        'template' 	=> $mail_template,
                        'vars'		=> $vars,
                    ], 8000);
                }

                $alert_service->success('E-mail bericht verzonden.');

                $link_render->redirect($vr->get('users_show'), $pp->ary(),
                    ['id' => $id]);

            }

            $alert_service->error($errors);
        }

        $count_messages = $db->fetchColumn('select count(*)
            from ' . $pp->schema() . '.messages
            where id_user = ?', [$id]);

        $count_transactions = $db->fetchColumn('select count(*)
            from ' . $pp->schema() . '.transactions
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
            $and_status = $pp->is_admin() ? '' : ' and u.status in (1, 2) ';
        }

        $next = $db->fetchColumn('select id
            from ' . $pp->schema() . '.users u
            where u.letscode > ?
            ' . $and_status . '
            order by u.letscode asc
            limit 1', $sql_bind);

        $prev = $db->fetchColumn('select id
            from ' . $pp->schema() . '.users u
            where u.letscode < ?
            ' . $and_status . '
            order by u.letscode desc
            limit 1', $sql_bind);

        $intersystem_missing = false;

        if ($pp->is_admin()
            && $user['accountrole'] === 'interlets'
            && $config_service->get_intersystem_en($pp->schema()))
        {
            $intersystem_id = $db->fetchColumn('select id
                from ' . $pp->schema() . '.letsgroups
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

        $contacts_response = $contacts_user_show_inline_controller(
            $user['id'],
            $db,
            $assets_service,
            $item_access_service,
            $link_render,
            $pp,
            $su,
            $distance_service,
            $account_render,
            $env_mapbox_token
        );

        $contacts_content = $contacts_response->getContent();

        $assets_service->add([
            'jqplot',
            'plot_user_transactions.js',
        ]);

        if ($pp->is_admin() || $s_owner)
        {
            $assets_service->add([
                'fileupload',
                'upload_image.js',
            ]);
        }

        if ($pp->is_admin() || $s_owner)
        {
            $title = $pp->is_admin() ? 'Gebruiker' : 'Mijn gegevens';

            $btn_top_render->edit($vr->get('users_edit'), $pp->ary(),
                ['id' => $id], $title . ' aanpassen');

            if ($pp->is_admin())
            {
                $btn_top_render->edit_pw('users_password_admin', $pp->ary(),
                ['id' => $id], 'Paswoord aanpassen');
            }
            else if ($s_owner)
            {
                $btn_top_render->edit_pw('users_password', $pp->ary(),
                    [], 'Paswoord aanpassen');
            }
        }

        if ($pp->is_admin() && !$count_transactions && !$s_owner)
        {
            $btn_top_render->del('users_del_admin', $pp->ary(),
                ['id' => $id], 'Gebruiker verwijderen');
        }

        if ($pp->is_admin()
            || (!$s_owner && $user['status'] !== 7
                && !($pp->is_guest() && $su->is_system_self())))
        {
            $tus = ['tuid' => $id];

            if (!$su->is_system_self())
            {
                $tus['tus'] = $pp->schema();
            }

            $btn_top_render->add_trans('transactions_add', $su->ary(),
                $tus, 'Transactie naar ' . $account_render->str($id, $pp->schema()));
        }

        $pp_status_ary = $pp->ary();
        $pp_status_ary['status'] = $status;

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $btn_nav_render->nav($vr->get('users_show'), $pp_status_ary,
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list($vr->get('users'), $pp_status_ary,
            [], 'Overzicht', 'users');

        $status_id = $user['status'];

        if (isset($user['adate']))
        {
            $status_id = ($config_service->get_new_user_treshold($pp->schema()) < strtotime($user['adate']) && $status_id == 1) ? 3 : $status_id;
        }

        $h_status_ary = StatusCnst::LABEL_ARY;
        $h_status_ary[3] = 'Instapper';

        if ($s_owner && !$pp->is_admin())
        {
            $heading_render->add('Mijn gegevens: ');
        }

        $heading_render->add_raw($account_render->link($id, $pp->ary()));

        if ($status_id != 1)
        {
            $heading_render->add_raw(' <small><span class="text-');
            $heading_render->add_raw(StatusCnst::CLASS_ARY[$status_id]);
            $heading_render->add_raw('">');
            $heading_render->add_raw($h_status_ary[$status_id]);
            $heading_render->add_raw('</span></small>');
        }

        if ($pp->is_admin())
        {
            if ($intersystem_missing)
            {
                $heading_render->add_raw(' <span class="label label-warning label-sm">');
                $heading_render->add_raw('<i class="fa fa-exclamation-triangle"></i> ');
                $heading_render->add_raw('De interSysteem-verbinding ontbreekt</span>');
            }
            else if ($intersystem_id)
            {
                $heading_render->add(' ');
                $heading_render->add_raw($link_render->link_fa('intersystems_show', $pp->ary(),
                    ['id' => $intersystem_id], 'Gekoppeld interSysteem',
                    ['class' => 'btn btn-default'], 'share-alt'));
            }
        }

        $heading_render->fa('user');

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
            $out .= $env_s3_url . $user['PictureFile'];
        }
        else
        {
            $out .= $assets_service->get('1.gif');
        }

        $out .= '" ';
        $out .= 'data-base-url="' . $env_s3_url . '">';

        $out .= '<div id="no_img"';
        $out .= $no_user_img;
        $out .= '>';
        $out .= '<i class="fa fa-user fa-5x text-muted"></i>';
        $out .= '<br>Geen profielfoto</div>';

        $out .= '</div>';

        if ($pp->is_admin() || $s_owner)
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

            if ($pp->is_admin())
            {
                $out .= $link_render->context_path('users_image_upload_admin', $pp->ary(),
                    ['id' => $id]);
            }
            else
            {
                $out .= $link_render->context_path('users_image_upload', $pp->ary(), []);
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

            if ($pp->is_admin())
            {
                $out .= $link_render->link_fa('users_image_del_admin', $pp->ary(),
                    ['id' => $id], 'Foto verwijderen',
                    array_merge($btn_del_attr, ['class' => 'btn btn-danger btn-lg btn-block']),
                    'times');
            }
            else
            {
                $out .= $link_render->link_fa('users_image_del', $pp->ary(),
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

        if ($pp->is_admin()
            || $s_owner
            || $item_access_service->is_visible_xdb($fullname_access))
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

        if ($pp->is_admin())
        {
            $out .= '<dt>';
            $out .= 'Zichtbaarheid Volledige Naam';
            $out .= '</dt>';
            $out .= '<dd>';
            $out .= $item_access_service->get_label_xdb($fullname_access);
            $out .= '</dd>';
        }

        $out .= '<dt>';
        $out .= 'Postcode';
        $out .= '</dt>';
        $out .= $this->get_dd($user['postcode'] ?? '');

        if ($pp->is_admin() || $s_owner)
        {
            $out .= '<dt>';
            $out .= 'Geboortedatum';
            $out .= '</dt>';

            if (isset($user['birthday']))
            {
                $out .= $date_format_service->get($user['birthday'], 'day', $pp->schema());
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

        if ($pp->is_admin())
        {
            $out .= '<dt>';
            $out .= 'Tijdstip aanmaak';
            $out .= '</dt>';

            if (isset($user['cdate']))
            {
                $out .= $this->get_dd($date_format_service->get($user['cdate'], 'min', $pp->schema()));
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
                $out .= $this->get_dd($date_format_service->get($user['adate'], 'min', $pp->schema()));
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
                $out .= $this->get_dd($date_format_service->get($user['lastlogin'], 'min', $pp->schema()));
            }
            else
            {
                $out .= '<dd><i class="fa fa-times"></i></dd>';
            }

            $out .= '<dt>';
            $out .= 'Rechten / rol';
            $out .= '</dt>';
            $out .= $this->get_dd(RoleCnst::LABEL_ARY[$user['accountrole']]);

            $out .= '<dt>';
            $out .= 'Status';
            $out .= '</dt>';
            $out .= $this->get_dd(StatusCnst::LABEL_ARY[$user['status']]);

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
        $out .= $config_service->get('currency', $pp->schema());
        $out .= '</dd>';

        $out .= '<dt>Minimum limiet</dt>';
        $out .= '<dd>';

        if (isset($user['minlimit']))
        {
            $out .= '<span class="label label-danger">';
            $out .= $user['minlimit'];
            $out .= '</span>&nbsp;';
            $out .= $config_service->get('currency', $pp->schema());
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        $out .= '<dt>Maximum limiet</dt>';
        $out .= '<dd>';

        if (isset($user['maxlimit']))
        {
            $out .= '<span class="label label-success">';
            $out .= $user['maxlimit'];
            $out .= '</span>&nbsp;';
            $out .= $config_service->get('currency', $pp->schema());
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        if ($pp->is_admin() || $s_owner)
        {
            $out .= '<dt>';
            $out .= 'Periodieke Overzichts E-mail';
            $out .= '</dt>';
            $out .= $user['cron_saldo'] ? 'Aan' : 'Uit';
            $out .= '</dl>';
        }

        $out .= '</div></div></div></div>';

        $out .= self::get_mail_form(
            $id,
            $user_mail_content,
            $user_mail_cc,
            $account_render,
            $form_token_service,
            $mail_addr_user_service,
            $pp,
            $su
        );

        $out .= $contacts_content;

        $out .= '<div class="row">';
        $out .= '<div class="col-md-12">';

        $out .= '<h3>Huidig saldo: <span class="label label-info">';
        $out .= $user['saldo'];
        $out .= '</span> ';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= '</h3>';
        $out .= '</div></div>';

        $out .= '<div class="row print-hide">';
        $out .= '<div class="col-md-6">';
        $out .= '<div id="chartdiv" data-height="480px" data-width="960px" ';

        $out .= 'data-plot-user-transactions="';
        $out .= htmlspecialchars($link_render->context_path('plot_user_transactions',
            $pp->ary(), ['user_id' => $id, 'days' => $tdays]));

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

        $account_str = $account_render->str($id, $pp->schema());

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

        $out .= $link_render->link_fa($vr->get('messages'),
            $pp->ary(),
            ['f' => ['uid' => $id]],
            'Vraag en aanbod van ' . $account_str .
            ' (' . $count_messages . ')',
            $attr_link_messages,
            'newspaper-o');

        $out .= $link_render->link_fa('transactions',
            $pp->ary(),
            ['f' => ['uid' => $id]],
            'Transacties van ' . $account_str .
            ' (' . $count_transactions . ')',
            $attr_link_transactions,
            'exchange');

        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
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
        int $user_id,
        string $user_mail_content,
        bool $user_mail_cc,
        AccountRender $account_render,
        FormTokenService $form_token_service,
        MailAddrUserService $mail_addr_user_service,
        PageParamsService $pp,
        SessionUserService $su
    ):string
    {
        $s_owner = !$pp->is_guest()
            && $su->is_system_self()
            && $su->id() === $user_id
            && $user_id;

        $mail_from = $su->schema()
            && !$su->is_master()
            && !$su->is_elas_guest()
                ? $mail_addr_user_service->get($su->id(), $su->schema())
                : [];

        $mail_to = $mail_addr_user_service->get($user_id, $pp->schema());

        $user_mail_disabled = true;

        if ($su->is_elas_guest())
        {
            $placeholder = 'Als eLAS gast kan je niet het E-mail formulier gebruiken.';
        }
        else if ($su->is_master())
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
        $out .=  $account_render->link($user_id, $pp->ary());
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

        $out .= $form_token_service->get_hidden_input();
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
