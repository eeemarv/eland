<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Command\Tags\TagsUsersCommand;
use App\Command\Users\UsersMailContactCommand;
use App\Controller\Contacts\ContactsUserShowInlineController;
use App\Form\Type\MailContact\MailContactType;
use App\Form\Type\Tags\TagsUsersType;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\AccountRepository;
use App\Repository\ContactRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DistanceService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\ResponseCacheService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersShowController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/{status}',
        name: 'users_show',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'id'            => '%assert.id%',
            'status'        => '%assert.account_status.all%',
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
        '/{system}/{role_short}/users/self/{status}',
        name: 'users_show_self',
        methods: ['GET', 'POST'],
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
        TagRepository $tag_repository,
        ContactRepository $contact_repository,
        AccountRepository $account_repository,
        UserRepository $user_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        UserCacheService $user_cache_service,
        DistanceService $distance_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        ResponseCacheService $response_cache_service,
        ContactsUserShowInlineController $contacts_user_show_inline_controller,
        string $env_s3_url,
        string $env_map_access_token,
        string $env_map_tiles_url
    ):Response
    {
        if (!$pp->is_admin()
            && !in_array($status, ['active', 'new', 'leaving', 'intersystem']))
        {
            throw new AccessDeniedHttpException('No access for this user status');
        }

        if (!$is_self
            && $su->is_owner($id)
        )
        {
            return $this->redirectToRoute('users_show_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $tdays = $request->query->get('tdays', '365');

        $user = $user_cache_service->get($id, $pp->schema());

        if (!$user)
        {
            throw new NotFoundHttpException(
                'The user with id ' . $id . ' not found');
        }

        if (!$pp->is_admin() && !$user['is_active'])
        {
            throw new AccessDeniedHttpException('You have no access to this user account.');
        }

        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);

        $tags_enabled = $config_service->get_bool('users.tags.enabled', $pp->schema());

        $messages_enabled = $config_service->get_bool('messages.enabled', $pp->schema());
        $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());
        $min_limit = $account_repository->get_min_limit($id, $pp->schema());
        $max_limit = $account_repository->get_max_limit($id, $pp->schema());
        $balance = $account_repository->get_balance($id, $pp->schema());

        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());

        $full_name_edit_en = $config_service->get_bool('users.fields.full_name.self_edit', $pp->schema());

        if (!$su->is_owner($id))
        {
            $full_name_edit_en = false;
        }

        if ($pp->is_admin())
        {
            $full_name_edit_en = true;
        }

        $status_def_ary = UsersListController::get_status_def_ary($config_service, $item_access_service, $pp);

        $tags_form = null;
        $render_tags = false;

        if ($pp->is_admin() && $tags_enabled)
        {
            $tags_command = new TagsUsersCommand();
            $tags_command->tags = $tag_repository->get_id_ary_for_user($id, $pp->schema(), active_only:true);

            $tags_form = $this->createForm(TagsUsersType::class,
                $tags_command);

            $tags_form->handleRequest($request);

            if ($tags_form->isSubmitted() &&
                $tags_form->isValid())
            {
                $tags_command = $tags_form->getData();

                $count_changes = $tag_repository->update_for_user($tags_command, $id, $su->id(), $pp->schema());
                $response_cache_service->clear_cache($pp->schema());
                $alert_service->success('Wijzigingen van tags opgeslagen. (' . $count_changes . ')');

                if ($is_self)
                {
                    return $this->redirectToRoute('users_show_self', $pp->ary());
                }

                return $this->redirectToRoute('users_show', [...$pp->ary(),
                    'id'    => $id,
                ]);
            }

            $render_tags = true;
        }

        $mail_command = new UsersMailContactCommand();

        $mail_form = $this->createForm(MailContactType::class, $mail_command, [
            'to_user_id'    => $id,
        ]);

        $mail_form->handleRequest($request);

        if ($mail_form->isSubmitted()
            && $mail_form->isValid())
        {
            $mail_command = $mail_form->getData();

            $from_user = $user_cache_service->get($su->id(), $su->schema());

            $vars = [
                'from_user'			=> $from_user,
                'from_schema'		=> $su->schema(),
                'to_user'			=> $user,
                'to_schema'			=> $pp->schema(),
                'is_same_system'	=> $su->is_system_self(),
                'msg_content'		=> $mail_command->message,
            ];

            $mail_template = $su->is_system_self()
                ? 'user_msg/msg'
                : 'user_msg/msg_intersystem';

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'to'		=> $mail_addr_user_service->get($id, $pp->schema()),
                'reply_to'	=> $mail_addr_user_service->get($su->id(), $su->schema()),
                'template'	=> $mail_template,
                'vars'		=> $vars,
            ], 8000);

            if ($mail_command->cc)
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

            return $this->redirectToRoute('users_show', [
                ...$pp->ary(),
                'id'        => $id,
                'status'    => $status,
            ]);
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

        $params['status'] = $status;

        /**
         * prev - next
         */

        $sql_map = [
            'where'     => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];

        $sql['prev'] = $sql_map;
        $sql['prev']['where'][] = 'u.code < ?';
        $sql['prev']['params'][] = $user['code'];
        $sql['prev']['types'][] = \PDO::PARAM_STR;

        $sql['next'] = $sql_map;
        $sql['next']['where'][] = 'u.code > ?';
        $sql['next']['params'][] = $user['code'];
        $sql['next']['types'][] = \PDO::PARAM_STR;

        $sql['status'] = $sql_map;

        foreach ($status_def_ary[$status]['sql'] as $st_def_key => $def_sql_ary)
        {
            foreach ($def_sql_ary as $def_val)
            {
                if (is_array($def_val) && $st_def_key = 'where')
                {
                    $wh_or = '(';
                    $wh_or .= implode(' or ', $def_val);
                    $wh_or .= ')';
                    $sql['status'][$st_def_key][] = $wh_or;
                    continue;
                }

                $sql['status'][$st_def_key][] = $def_val;
            }
        }

        $sql_prev = $sql;
        unset($sql_prev['next']);
        $sql_prev_where = implode(' and ', array_merge(...array_column($sql_prev, 'where')));
        $sql_prev_params = array_merge(...array_column($sql_prev, 'params'));
        $sql_prev_types = array_merge(...array_column($sql_prev, 'types'));

        $sql_next = $sql;
        unset($sql_next['prev']);
        $sql_next_where = implode(' and ', array_merge(...array_column($sql_next, 'where')));
        $sql_next_params = array_merge(...array_column($sql_next, 'params'));
        $sql_next_types = array_merge(...array_column($sql_next, 'types'));

        $next_id = $db->fetchOne('select id
            from ' . $pp->schema() . '.users u
            where
            ' . $sql_next_where . '
            order by u.code asc
            limit 1',
            $sql_next_params,
            $sql_next_types);

        $prev_id = $db->fetchOne('select id
            from ' . $pp->schema() . '.users u
            where
            ' . $sql_prev_where . '
            order by u.code desc
            limit 1',
            $sql_prev_params,
            $sql_prev_types);

        $last_login = null;

        if ($pp->is_admin())
        {
            $last_login = $user_repository->get_last_login_datetime_str($id, $pp->schema());
        }

        $contacts_response = $contacts_user_show_inline_controller(
            $user['id'],
            $contact_repository,
            $item_access_service,
            $link_render,
            $pp,
            $su,
            $distance_service,
            $account_render,
            $env_map_access_token,
            $env_map_tiles_url
        );

        $uct = $contacts_response->getContent();

        $pip = '<div class="panel panel-default">';
        $pip .= '<div class="panel-body text-center ';
        $pip .= 'center-block img-upload" id="img_user">';

        $show_img = $user['image_file'] ? true : false;

        $user_img = $show_img ? '' : ' style="display:none;"';
        $no_user_img = $show_img ? ' style="display:none;"' : '';

        $pip .= '<img id="img"';
        $pip .= $user_img;
        $pip .= ' class="img-rounded img-responsive center-block w-100" ';
        $pip .= 'src="';

        if ($user['image_file'])
        {
            $pip .= $env_s3_url . $user['image_file'];
        }
        else
        {
            $pip .= $assets_service->get('1.gif');
        }

        $pip .= '" ';
        $pip .= 'data-base-url="' . $env_s3_url . '">';

        $pip .= '<div id="no_img"';
        $pip .= $no_user_img;
        $pip .= '>';
        $pip .= '<i class="fa fa-';
        $pip .= $is_intersystem ? 'share-alt' : 'user';
        $pip .= ' fa-5x text-muted"></i>';
        $pip .= '<br>Geen profielfoto/afbeelding</div>';

        $pip .= '</div>';

        if ($pp->is_admin() || $su->is_owner($id))
        {
            $btn_del_attr = ['id'	=> 'btn_remove'];

            if (!$user['image_file'])
            {
                $btn_del_attr['style'] = 'display:none;';
            }

            $pip .= '<div class="panel-footer">';
            $pip .= '<span class="btn btn-success btn-lg btn-block fileinput-button">';
            $pip .= '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
            $pip .= '<input type="file" name="image" ';
            $pip .= 'data-url="';

            if ($pp->is_admin())
            {
                $pip .= $link_render->context_path('users_image_upload_admin', $pp->ary(),
                    ['id' => $id]);
            }
            else
            {
                $pip .= $link_render->context_path('users_image_upload', $pp->ary(), []);
            }

            $pip .= '" data-image-crop data-fileupload ';
            $pip .= 'data-message-file-type-not-allowed="Bestandstype is niet toegelaten." ';
            $pip .= 'data-message-max-file-size="Het bestand is te groot." ';
            $pip .= 'data-message-min-file-size="Het bestand is te klein." ';
            $pip .= 'data-message-uploaded-bytes="Het bestand is te groot." ';
            $pip .= '></span>';

            $pip .= '<p class="text-warning">';
            $pip .= 'Toegestane formaten: jpg/jpeg, png, webp, gif, svg. ';
            $pip .= 'Je kan ook een afbeelding hierheen verslepen.</p>';

            if ($pp->is_admin())
            {
                $pip .= $link_render->link_fa('users_image_del_admin', $pp->ary(),
                    ['id' => $id], 'Afbeelding verwijderen', [
                        ...$btn_del_attr,
                        'class' => 'btn btn-danger btn-lg btn-block',
                    ],
                    'times');
            }
            else
            {
                $pip .= $link_render->link_fa('users_image_del', $pp->ary(),
                    [], 'Afbeelding verwijderen', [
                        ...$btn_del_attr,
                        'class' => 'btn btn-danger btn-lg btn-block',
                    ],
                    'times');
            }

            $pip .= '</div>';
        }
        $pip .= '</div>';

        $tmi = '';

        if ($transactions_enabled)
        {
            $tmi = '<div class="row">';
            $tmi .= '<div class="col-md-12">';

            $tmi .= '<h3>Huidig saldo: <span class="label label-info">';
            $tmi .= $balance;
            $tmi .= '</span> ';
            $tmi .= $currency;
            $tmi .= '</h3>';
            $tmi .= '</div></div>';

            $tmi .= '<div class="row print-hide">';
            $tmi .= '<div class="col-md-6">';
            $tmi .= '<div id="chartdiv" data-height="480px" data-width="960px" ';

            $tmi .= 'data-transactions-plot-user="';
            $tmi .= htmlspecialchars($link_render->context_path('transactions_plot_user',
                $pp->ary(), ['user_id' => $id, 'days' => $tdays]));

            $tmi .= '">';
            $tmi .= '</div>';
            $tmi .= '</div>';

            $tmi .= '<div class="col-md-6">';
            $tmi .= '<div id="donutdiv" data-height="480px" ';
            $tmi .= 'data-width="960px"></div>';
            $tmi .= '<h4>Interacties laatste jaar</h4>';
            $tmi .= '</div>';
            $tmi .= '</div>';
        }

        if (!$is_self
            && !$is_intersystem
            && ($messages_enabled || $transactions_enabled))
        {
            $tmi .= '<div class="row">';
            $tmi .= '<div class="col-md-12">';

            $tmi .= '<div class="panel panel-default">';
            $tmi .= '<div class="panel-body">';

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
                $tmi .= $link_render->link_fa($vr->get('messages'),
                    $pp->ary(),
                    ['uid' => $id],
                    'Vraag en aanbod van ' . $account_str .
                    ' (' . $count_messages . ')',
                    $attr_link_messages,
                    'newspaper-o');
            }

            if ($transactions_enabled)
            {
                $tmi .= $link_render->link_fa('transactions',
                    $pp->ary(),
                    ['uid' => $id],
                    'Transacties van ' . $account_str .
                    ' (' . $count_transactions . ')',
                    $attr_link_transactions,
                    'exchange');
            }

            $tmi .= '</div>';
            $tmi .= '</div>';
            $tmi .= '</div>';
            $tmi .= '</div>';
        }

        return $this->render('users/users_show.html.twig', [
            'profile_image_panel_raw'   => $pip,
            'user_contacts_table_raw'   => $uct,
            'transactions_messages_raw' => $tmi,
            'mail_form'             => $mail_form,
            'user'                  => $user,
            'balance'               => $balance,
            'min_limit'             => $min_limit,
            'max_limit'             => $max_limit,
            'last_login'            => $last_login,
            'id'                    => $id,
            'status'                => $status,
            'is_self'               => $is_self,
            'full_name_edit_en'     => $full_name_edit_en,
            'prev_id'               => $prev_id,
            'next_id'               => $next_id,
            'count_transactions'    => $count_transactions,
            'count_messages'        => $count_messages,
            'is_intersystem'        => $is_intersystem,
            'tags_form'             => $tags_form,
            'render_tags'           => $render_tags,
        ]);
    }
}
