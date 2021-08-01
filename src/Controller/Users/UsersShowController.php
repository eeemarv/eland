<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cnst\BulkCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Cnst\StatusCnst;
use App\Cnst\RoleCnst;
use App\Controller\Contacts\ContactsUserShowInlineController;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\AccountRepository;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\DistanceService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

class UsersShowController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/{status}',
        name: 'users_show',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'id'            => '%assert.id%',
            'status'        => '%assert.account_status%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'is_self'       => false,
            'status'        => 'active',
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/self',
        name: 'users_show_self',
        methods: ['GET'],
        priority: 10,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'is_self'       => true,
            'status'        => 'active',
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        string $status,
        int $id,
        bool $is_self,
        Db $db,
        AccountRepository $account_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
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
        ContactsUserShowInlineController $contacts_user_show_inline_controller,
        string $env_s3_url,
        string $env_map_access_token,
        string $env_map_tiles_url
    ):Response
    {
        if (!$pp->is_admin()
            && !in_array($status, ['active', 'new', 'leaving']))
        {
            throw new AccessDeniedHttpException('No access for this user status');
        }

        if ($id === 0 && $is_self)
        {
            $id = $su->id();
        }

        $full_name_enabled = $config_service->get_bool('users.fields.full_name.enabled', $pp->schema());
        $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());
        $birthday_enabled = $config_service->get_bool('users.fields.birthday.enabled', $pp->schema());
        $hobbies_enabled = $config_service->get_bool('users.fields.hobbies.enabled', $pp->schema());
        $comments_enabled = $config_service->get_bool('users.fields.comments.enabled', $pp->schema());
        $admin_comments_enabled = $config_service->get_bool('users.fields.admin_comments.enabled', $pp->schema());
        $periodic_mail_enabled = $config_service->get_bool('periodic_mail.enabled', $pp->schema());

        $errors = [];

        $tdays = $request->query->get('tdays', '365');

        $user_mail_content = $request->request->get('user_mail_content', '');
        $user_mail_cc = $request->request->get('user_mail_cc', '') ? true : false;
        $user_mail_submit = $request->request->get('user_mail_submit', '') ? true : false;

        $user_mail_cc = $request->isMethod('POST') ? $user_mail_cc : true;

        $user = $user_cache_service->get($id, $pp->schema());

        if (!$user)
        {
            throw new NotFoundHttpException(
                'The user with id ' . $id . ' not found');
        }

        if (!$pp->is_admin() && !in_array($user['status'], [1, 2]))
        {
            throw new AccessDeniedHttpException('You have no access to this user account.');
        }

        $messages_enabled = $config_service->get_bool('messages.enabled', $pp->schema());
        $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());
        $limits_enabled = $config_service->get_bool('accounts.limits.enabled', $pp->schema());
        $min_limit = $account_repository->get_min_limit($id, $pp->schema());
        $max_limit = $account_repository->get_max_limit($id, $pp->schema());
        $balance = $account_repository->get_balance($id, $pp->schema());

        $system_min_limit = $config_service->get_int('accounts.limits.global.min', $pp->schema());
        $system_max_limit = $config_service->get_int('accounts.limits.global.max', $pp->schema());
        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());

        $status_def_ary = UsersListController::get_status_def_ary($config_service, $item_access_service, $pp);

        // process mail form

        if ($request->isMethod('POST') && $user_mail_submit)
        {
            if ($su->is_master())
            {
                throw new AccessDeniedHttpException('The master account can not send emails');
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

                return $this->redirectToRoute('users_show', array_merge($pp->ary(),
                    ['id' => $id]));
            }

            $alert_service->error($errors);
        }

        $count_messages = $db->fetchOne('select count(*)
            from ' . $pp->schema() . '.messages
            where user_id = ?',
            [$id], [\PDO::PARAM_INT]);

        $count_transactions = $db->fetchOne('select count(*)
            from ' . $pp->schema() . '.transactions
            where id_from = ?
                or id_to = ?',
                [$id, $id], [\PDO::PARAM_INT, \PDO::PARAM_INT]);

        $sql_next = [
            'where'     => ['u.code > ?'],
            'params'    => [$user['code']],
            'types'     => [\PDO::PARAM_STR],
        ];
        $sql_prev = [
            'where'     => ['u.code < ?'],
            'params'    => [$user['code']],
            'types'     => [\PDO::PARAM_STR],
        ];

        foreach ($status_def_ary[$status]['sql'] as $st_def_key => $def_sql_ary)
        {
            foreach ($def_sql_ary as $def_val)
            {
                $sql_next[$st_def_key][] = $def_val;
                $sql_prev[$st_def_key][] = $def_val;
            }
        }

        $params['status'] = $status;

        $sql_next_where = implode(' and ', $sql_next['where']);
        $sql_prev_where = implode(' and ', $sql_prev['where']);

        $next_id = $db->fetchOne('select id
            from ' . $pp->schema() . '.users u
            where
            ' . $sql_next_where . '
            order by u.code asc
            limit 1',
            $sql_next['params'],
            $sql_next['types']);

        $prev_id = $db->fetchOne('select id
            from ' . $pp->schema() . '.users u
            where
            ' . $sql_prev_where . '
            order by u.code desc
            limit 1',
            $sql_prev['params'],
            $sql_prev['types']);

        $intersystem_missing = false;

        if ($pp->is_admin()
            && $user['role'] === 'guest'
            && $config_service->get_intersystem_en($pp->schema()))
        {
            $intersystem_id = $db->fetchOne('select id
                from ' . $pp->schema() . '.letsgroups
                where localletscode = ?',
                [$user['code']],
                [\PDO::PARAM_STR]);

            if (!$intersystem_id)
            {
                $intersystem_missing = true;
            }
        }
        else
        {
            $intersystem_id = 0;
        }

        if ($pp->is_admin())
        {
            $last_login = $db->fetchOne('select max(created_at)
                from ' . $pp->schema() . '.login
                where user_id = ?',
                [$id],
                [\PDO::PARAM_INT]);
        }

        $contacts_response = $contacts_user_show_inline_controller(
            $user['id'],
            $db,
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

        $out = '<div class="row">';
        $out .= '<div class="col-md-6">';

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-body text-center ';
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

            $out .= '<div class="panel-footer">';
            $out .= '<span class="btn btn-success btn-lg btn-block fileinput-button">';
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

            $out .= '" data-image-crop data-fileupload ';
            $out .= 'data-message-file-type-not-allowed="Bestandstype is niet toegelaten." ';
            $out .= 'data-message-max-file-size="Het bestand is te groot." ';
            $out .= 'data-message-min-file-size="Het bestand is te klein." ';
            $out .= 'data-message-uploaded-bytes="Het bestand is te groot." ';
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

        $out .= '<div class="panel panel-default printview">';
        $out .= '<div class="panel-heading">';
        $out .= '<dl>';

        if ($full_name_enabled)
        {
            $full_name_access = $user['full_name_access'] ?? 'admin';

            $out .= '<dt>';
            $out .= 'Volledige naam';
            $out .= '</dt>';

            if ($pp->is_admin()
                || $su->is_owner($id)
                || $item_access_service->is_visible($full_name_access))
            {
                $out .= $this->get_dd($user['full_name'] ?? '');
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
                $out .= $item_access_service->get_label($full_name_access);
                $out .= '</dd>';
            }
        }

        if ($postcode_enabled)
        {
            $out .= '<dt>';
            $out .= 'Postcode';
            $out .= '</dt>';
            $out .= $this->get_dd($user['postcode'] ?? '');
        }

        if ($birthday_enabled)
        {
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
        }

        if ($hobbies_enabled)
        {
            $out .= '<dt>';
            $out .= 'Hobbies / Interesses';
            $out .= '</dt>';
            $out .= $this->get_dd($user['hobbies'] ?? '');
        }

        if ($comments_enabled)
        {
            $out .= '<dt>';
            $out .= 'Commentaar';
            $out .= '</dt>';
            $out .= $this->get_dd($user['comments'] ?? '');
        }

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

            if ($admin_comments_enabled)
            {
                $out .= '<dt>';
                $out .= 'Commentaar van de admin';
                $out .= '</dt>';
                $out .= $this->get_dd($user['admin_comments'] ?? '');
            }
        }

        if ($transactions_enabled)
        {
            $out .= '<dt>Saldo</dt>';
            $out .= '<dd>';
            $out .= '<span class="label label-info">';
            $out .= $balance;
            $out .= '</span>&nbsp;';
            $out .= $currency;
            $out .= '</dd>';

            if ($limits_enabled)
            {
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
            }
        }

        if ($periodic_mail_enabled
            && ($pp->is_admin() || $su->is_owner($id)))
        {
            $out .= '<dt>';
            $out .= 'Periodieke Overzichts E-mail';
            $out .= '</dt>';
            $out .= $user['periodic_overview_en'] ? 'Aan' : 'Uit';
            $out .= '</dl>';
        }

        $out .= '</div></div></div></div>';

        if (!$is_self)
        {
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
        }

        $out .= $contacts_content;

        if ($transactions_enabled)
        {
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
        }

        if (!$is_self && ($messages_enabled || $transactions_enabled))
        {
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

            if ($messages_enabled)
            {
                $out .= $link_render->link_fa($vr->get('messages'),
                    $pp->ary(),
                    ['f' => ['uid' => $id]],
                    'Vraag en aanbod van ' . $account_str .
                    ' (' . $count_messages . ')',
                    $attr_link_messages,
                    'newspaper-o');
            }

            if ($transactions_enabled)
            {
                $out .= $link_render->link_fa('transactions',
                    $pp->ary(),
                    ['f' => ['uid' => $id]],
                    'Transacties van ' . $account_str .
                    ' (' . $count_transactions . ')',
                    $attr_link_transactions,
                    'exchange');
            }

            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        return $this->render('users/users_show.html.twig', [
            'content'   => $out,
            'id'        => $id,
            'status'    => $status,
            'is_self'   => $is_self,
            'prev_id'   => $prev_id,
            'next_id'   => $next_id,
            'count_transactions'    => $count_transactions,
            'count_messages'        => $count_messages,
            'intersystem_missing'   => $intersystem_missing,
            'intersystem_id'        => $intersystem_id,
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
