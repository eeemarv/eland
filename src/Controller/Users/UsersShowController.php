<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cnst\BulkCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Cnst\StatusCnst;
use App\Cnst\RoleCnst;
use App\Controller\Contacts\ContactsUserShowInlineController;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Repository\UserRepository;
use App\Repository\AccountRepository;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UsersShowController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $status,
        int $id,
        Db $db,
        UserRepository $user_repository,
        AccountRepository $account_repository,
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
        string $env_map_access_token,
        string $env_map_tiles_url
    ):Response
    {
        $errors = [];

        $tdays = $request->query->get('tdays', '365');

        $user_mail_content = $request->request->get('user_mail_content', '');
        $user_mail_cc = $request->request->get('user_mail_cc', '') ? true : false;
        $user_mail_submit = $request->request->get('user_mail_submit', '') ? true : false;

        $user_mail_cc = $request->isMethod('POST') ? $user_mail_cc : true;

        $user = $user_repository->get($id, $pp->schema());

        if (!$user)
        {
            throw new NotFoundHttpException(
                'De gebruiker met id ' . $id . ' bestaat niet');
        }

        if (!$pp->is_admin() && !in_array($user['status'], [1, 2]))
        {
            throw new AccessDeniedHttpException('Access denied.');
        }

        $min_limit = $account_repository->get_min_limit($id, $pp->schema());
        $max_limit = $account_repository->get_max_limit($id, $pp->schema());
        $balance = $account_repository->get_balance($id, $pp->schema());

        $system_min_limit = $config_service->get_int('accounts.limits.global.min', $pp->schema());
        $system_max_limit = $config_service->get_int('accounts.limits.global.max', $pp->schema());
        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

        $status_def_ary = UsersListController::get_status_def_ary($config_service, $pp);

        // process mail form

        if ($request->isMethod('POST') && $user_mail_submit)
        {
            if ($su->is_master())
            {
                throw new AccessDeniedHttpException('Het master account kan
                    geen E-mail berichten versturen.');
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
                $from_user = $user_cache_service->get($su->id(), $su->schema());

                $vars = [
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

                $link_render->redirect('users_show', $pp->ary(),
                    ['id' => $id]);

            }

            $alert_service->error($errors);
        }

        $count_messages = $db->fetchColumn('select count(*)
            from ' . $pp->schema() . '.messages
            where user_id = ?', [$id]);

        $count_transactions = $db->fetchColumn('select count(*)
            from ' . $pp->schema() . '.transactions
            where id_from = ?
                or id_to = ?', [$id, $id]);

        $sql_nxt_prv = [
            'where'     => [],
            'params'    => [$user['code']],
            'types'     => [\PDO::PARAM_STR],
        ];

        foreach ($status_def_ary[$status]['sql'] as $st_def_key => $def_sql_ary)
        {
            foreach ($def_sql_ary as $def_val)
            {
                $sql_nxt_prv[$st_def_key][] = $def_val;
            }
        }

        $params['status'] = $status;

        $sql_nxt_prv_where = ' and ' . implode(' and ', $sql_nxt_prv['where']);

        $next = $db->fetchColumn('select id
            from ' . $pp->schema() . '.users u
            where u.code > ?
            ' . $sql_nxt_prv_where . '
            order by u.code asc
            limit 1', $sql_nxt_prv['params'], 0, $sql_nxt_prv['types']);

        $prev = $db->fetchColumn('select id
            from ' . $pp->schema() . '.users u
            where u.code < ?
            ' . $sql_nxt_prv_where . '
            order by u.code desc
            limit 1', $sql_nxt_prv['params'], 0, $sql_nxt_prv['types']);

        $intersystem_missing = false;

        if ($pp->is_admin()
            && $user['role'] === 'guest'
            && $config_service->get_intersystem_en($pp->schema()))
        {
            $intersystem_id = $db->fetchColumn('select id
                from ' . $pp->schema() . '.letsgroups
                where localletscode = ?', [$user['code']], 0, [\PDO::PARAM_STR]);

            if (!$intersystem_id)
            {
                $intersystem_missing = true;
            }
        }
        else
        {
            $intersystem_id = false;
        }

        if ($pp->is_admin())
        {
            $last_login = $db->fetchColumn('select max(created_at)
                from ' . $pp->schema() . '.login
                where user_id = ?', [$id], 0, [\PDO::PARAM_INT]);
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
            $env_map_access_token,
            $env_map_tiles_url
        );

        $contacts_content = $contacts_response->getContent();

        $assets_service->add([
            'jqplot',
            'plot_user_transactions.js',
        ]);

        if ($pp->is_admin() || $su->is_owner($id))
        {
            $assets_service->add([
                'fileupload',
                'upload_image.js',
            ]);
        }

        if ($pp->is_admin() || $su->is_owner($id))
        {
            $title = $pp->is_admin() ? 'Gebruiker' : 'Mijn gegevens';

            $btn_top_render->edit($vr->get('users_edit'), $pp->ary(),
                ['id' => $id], $title . ' aanpassen');

            if ($pp->is_admin())
            {
                $btn_top_render->edit_pw('users_password_set_admin', $pp->ary(),
                ['id' => $id], 'Paswoord aanpassen');
            }
            else if ($su->is_owner($id))
            {
                $btn_top_render->edit_pw('users_password_set', $pp->ary(),
                    [], 'Paswoord aanpassen');
            }
        }

        if ($pp->is_admin() && !$count_transactions && !$su->is_owner($id))
        {
            $btn_top_render->del('users_del_admin', $pp->ary(),
                ['id' => $id], 'Gebruiker verwijderen');
        }

        if ($pp->is_admin()
            || (!$su->is_owner($id) && $user['status'] !== 7
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

        $btn_nav_render->nav('users_show', $pp_status_ary,
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list($vr->get('users'), $pp_status_ary,
            [], 'Overzicht', 'users');

        $status_id = $user['status'];

        if (isset($user['adate'])
            && $status_id === 1
            && $new_user_treshold->getTimestamp() < strtotime($user['adate'] . ' UTC')
        )
        {
            $status_id = 3;
        }

        $h_status_ary = StatusCnst::LABEL_ARY;
        $h_status_ary[3] = 'Instapper';

        if ($su->is_owner($id) && !$pp->is_admin())
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

        $out .= '<div class="card card-default" data-fileupload-container>';
        $out .= '<div class="card-body text-center ';
        $out .= 'center-block img-upload" id="img_user">';

        $show_img = $user['image_file'] ? true : false;

        $user_img = $show_img ? '' : ' style="display:none;"';
        $no_user_img = $show_img ? ' style="display:none;"' : '';

        $out .= '<img id="img"';
        $out .= $user_img;
        $out .= ' class="img-rounded img-responsive center-block w-100" ';
        $out .= 'src="';

        if ($user['image_file'])
        {
            $out .= $env_s3_url . $user['image_file'];
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
        $out .= '<br>Geen profielfoto/afbeelding</div>';

        $out .= '</div>';

        if ($pp->is_admin() || $su->is_owner($id))
        {
            $btn_del_attr = ['id'	=> 'btn_remove'];

            if (!$user['image_file'])
            {
                $btn_del_attr['style'] = 'display:none;';
            }

            $out .= '<div class="card-footer">';
            $out .= '<span class="btn btn-success btn-lg btn-block fileinput-button" data-fileupload-btn>';
            $out .= '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
            $out .= '<input type="file" name="image" ';
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

            $out .= '" data-fileupload-btn-input ';
            $out .= 'data-error-file-type="Bestandstype is niet toegelaten." ';
            $out .= 'data-error-max-file-size="Het bestand is te groot." ';
            $out .= 'data-error-min-file-size="Het bestand is te klein." ';
            $out .= 'data-error-uploaded-bytes="Het bestand is te groot." ';
            $out .= '></span>';

            $out .= '<p class="text-warning">';
            $out .= 'Toegestane formaten: jpg/jpeg, png, gif, svg. ';
            $out .= 'Je kan ook een afbeelding hierheen verslepen.</p>';

            if ($pp->is_admin())
            {
                $out .= $link_render->link_fa('users_image_del_admin', $pp->ary(),
                    ['id' => $id], 'Afbeelding verwijderen',
                    array_merge($btn_del_attr, ['class' => 'btn btn-danger btn-lg btn-block']),
                    'times');
            }
            else
            {
                $out .= $link_render->link_fa('users_image_del', $pp->ary(),
                    [], 'Afbeelding verwijderen',
                    array_merge($btn_del_attr, ['class' => 'btn btn-danger btn-lg btn-block']),
                    'times');
            }

            $out .= '</div>';
        }

        $out .= '</div></div>';

        $out .= '<div class="col-md-6">';

        $out .= '<div class="card bg-default printview">';
        $out .= '<div class="card-body">';
        $out .= '<dl>';

        $fullname_access = $user['fullname_access'] ?: 'admin';

        $out .= '<dt>';
        $out .= 'Volledige naam';
        $out .= '</dt>';

        if ($pp->is_admin()
            || $su->is_owner($id)
            || $item_access_service->is_visible($fullname_access))
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

        if ($pp->is_admin() || $su->is_owner($id))
        {
            $out .= '<dt>';
            $out .= 'Zichtbaarheid Volledige Naam';
            $out .= '</dt>';
            $out .= '<dd>';
            $out .= $item_access_service->get_label($fullname_access);
            $out .= '</dd>';
        }

        $out .= '<dt>';
        $out .= 'Postcode';
        $out .= '</dt>';
        $out .= $this->get_dd($user['postcode'] ?? '');

        if ($pp->is_admin() || $su->is_owner($id))
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

            $out .= $this->get_dd($date_format_service->get($user['created_at'], 'min', $pp->schema()));

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

            if (isset($last_login))
            {
                $out .= $this->get_dd($date_format_service->get($last_login, 'min', $pp->schema()));
            }
            else
            {
                $out .= '<dd><i class="fa fa-times"></i></dd>';
            }

            $out .= '<dt>';
            $out .= 'Rechten / rol';
            $out .= '</dt>';
            $out .= $this->get_dd(RoleCnst::LABEL_ARY[$user['role']]);

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
        $out .= $balance;
        $out .= '</span>&nbsp;';
        $out .= $currency;
        $out .= '</dd>';

        $out .= '<dt>Minimum limiet</dt>';
        $out .= '<dd>';

        if (isset($min_limit))
        {
            $out .= '<span class="label label-danger">';
            $out .= $min_limit;
            $out .= '</span>&nbsp;';
            $out .= $currency;
        }
        else if (isset($system_min_limit))
        {
            $out .= '<span class="label label-default">';
            $out .= $system_min_limit;
            $out .= '</span>&nbsp;';
            $out .= $currency;
            $out .= ' (Minimum Systeemslimiet)';
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        $out .= '<dt>Maximum limiet</dt>';
        $out .= '<dd>';

        if (isset($max_limit))
        {
            $out .= '<span class="label label-success">';
            $out .= $max_limit;
            $out .= '</span>&nbsp;';
            $out .= $currency;
        }
        else if (isset($system_max_limit))
        {
            $out .= '<span class="label label-default">';
            $out .= $system_max_limit;
            $out .= '</span>&nbsp;';
            $out .= $currency;
            $out .= ' (Maximum Systeemslimiet)';
        }
        else
        {
            $out .= '<i class="fa fa-times"></i>';
        }

        $out .= '</dd>';

        if ($pp->is_admin() || $su->is_owner($id))
        {
            $out .= '<dt>';
            $out .= 'Periodieke Overzichts E-mail';
            $out .= '</dt>';
            $out .= $user['periodic_overview_en'] ? 'Aan' : 'Uit';
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
        $out .= $balance;
        $out .= '</span> ';
        $out .= $currency;
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

        $out .= '<div class="card card-default">';
        $out .= '<div class="card-body">';

        $account_str = $account_render->str($id, $pp->schema());

        $attr_link_messages = $attr_link_transactions = [
            'class'     => 'btn btn-default btn-lg btn-block border border-secondary',
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

        return $this->render('users/users_show.html.twig', [
            'content'   => $out,
            'user'      => $user,
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
        $mail_from = $mail_addr_user_service->get($su->id(), $su->schema());
        $mail_to = $mail_addr_user_service->get($user_id, $pp->schema());

        $user_mail_disabled = true;

        if ($su->is_master())
        {
            $placeholder = 'Het master account kan geen berichten versturen.';
        }
        else if ($su->is_owner($user_id))
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

        $out .= '<div class="card fcard fcard-info mb-3">';
        $out .= '<div class="card-body">';

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

        $user_mail_cc_attr = $user_mail_cc ? ' checked' : '';
        $user_mail_cc_attr .= $user_mail_disabled ? ' disabled' : '';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'        => 'user_mail_cc',
            '%label%'       => 'Stuur een kopie naar mijzelf',
            '%attr%'        => $user_mail_cc_attr,
        ]);

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