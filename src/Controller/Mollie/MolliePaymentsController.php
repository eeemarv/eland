<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Cnst\BulkCnst;
use App\Command\Mollie\MollieFilterCommand;
use App\Form\Type\Mollie\MollieFilterType;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsController]
class MolliePaymentsController extends AbstractController
{
    const STATUS_RENDER = [
        'open'      => [
            'label'         => 'open',
            'class'         => 'warning',
        ],
        'paid'     => [
            'label'         => 'betaald',
            'class'         => 'success',
        ],
        'canceled'  => [
            'label'     => 'geannuleerd',
            'class'     => 'default-2',
        ],
    ];

    #[Route(
        '/{system}/{role_short}/mollie/payments',
        name: 'mollie_payments',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'mollie',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        AccountRender $account_render,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MailQueue $mail_queue,
        MailAddrUserService $mail_addr_user_service,
        DateFormatService $date_format_service,
        PageParamsService $pp,
        SessionUserService $su,
        TagAwareCacheInterface $cache,
        #[Autowire(service: 'html_sanitizer.sanitizer.admin_email_sanitizer')] HtmlSanitizerInterface $html_sanitizer,
        LoggerInterface $logger
    ):Response
    {
        if (!$config_service->get_bool('mollie.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Mollie submodule (users) not enabled.');
        }

        $errors = [];

        $new_users_days = $config_service->get_int('users.new.days', $pp->schema());
        $new_users_enabled = $config_service->get_bool('users.new.enabled', $pp->schema());
        $leaving_users_enabled = $config_service->get_bool('users.leaving.enabled', $pp->schema());

        $show_new_status = $new_users_enabled;

        if ($show_new_status)
        {
            $new_users_access = $config_service->get_str('users.new.access', $pp->schema());
            $show_new_status = $item_access_service->is_visible($new_users_access);
        }

        $show_leaving_status = $leaving_users_enabled;

        if ($show_leaving_status)
        {
            $leaving_users_access = $config_service->get_str('users.leaving.access', $pp->schema());
            $show_leaving_status = $item_access_service->is_visible($leaving_users_access);
        }

        $filter_command = new MollieFilterCommand();

        $filter_form = $this->createForm(MollieFilterType::class, $filter_command);
        $filter_form->handleRequest($request);
        $filter_command = $filter_form->getData();

        $f_params = $request->query->all('f');
        $filter_form_error = isset($f_params['user']) && !isset($filter_command->user);

        $pag = $request->query->all('p');
        $sort = $request->query->all('s');

        $selected = $request->request->all('sel');
        $bulk_mail_subject = $request->request->get('bulk_mail_subject', '');
        $bulk_mail_content = $request->request->get('bulk_mail_content', '');
        $bulk_mail_cc = $request->request->has('bulk_mail_cc');
        $bulk_mail_verify = $request->request->has('bulk_mail_verify');
        $bulk_mail_submit = $request->request->has('bulk_mail_submit');
        $bulk_cancel_verify = $request->request->has('bulk_cancel_verify');
        $bulk_cancel_submit = $request->request->has('bulk_cancel_submit');

        $mollie_apikey = $config_service->get_str('mollie.apikey', $pp->schema());

        if (!$mollie_apikey ||
            !(str_starts_with($mollie_apikey, 'test_')
            || str_starts_with($mollie_apikey, 'live_')))
        {
            if ($request->isMethod('GET'))
            {
                $alert_service->warning('Betalingen met Mollie zijn niet mogelijk want
                    er is geen Mollie apikey ingesteld in de ' .
                    $link_render->link('mollie_config', $pp->ary(), [], 'configuratie', []), false);
            }

            $no_mollie_apikey = true;
        }
        else if (!str_starts_with($mollie_apikey, 'live_'))
        {
            if ($request->isMethod('GET'))
            {
                $alert_service->warning('Er is geen <code>live_</code> Mollie apikey ingsteld in de ' .
                    $link_render->link('mollie_config', $pp->ary(), [], 'configuratie', []) .
                    '. Betalingen kunnen niet uitgevoerd worden!', false);
            }
        }

        $params = [
            's'	=> [
                'orderby'	=> $sort['orderby'] ?? 'p.created_at',
                'asc'		=> $sort['asc'] ?? 0,
            ],
            'p'	=> [
                'start'		=> $pag['start'] ?? 0,
                'limit'		=> $pag['limit'] ?? 100,
            ],
        ];

        $sql_map = [
            'where'     => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];
        $sql['common'] = $sql_map;
        $sql['common']['where'][] = '1 = 1';

        if (isset($filter_command->q))
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][] = 'r.description ilike ?';
            $sql['q']['params'][] = '%' . $filter_command->q . '%';
            $sql['q']['types'][] = \PDO::PARAM_STR;
        }

        if (isset($filter_command->user))
        {
            $sql['user']['where'][] = 'u.id = ?';
            $sql['user']['params'][] = $filter_command->user;
            $sql['user']['types'][] = \PDO::PARAM_INT;
        }

        if (isset($filter_command->status) && $filter_command->status)
        {
            $wh_or = [];
            $sql['status'] = $sql_map;

            if (in_array('open', $filter_command->status))
            {
                $wh_or[] = '(not p.is_paid and not p.is_canceled)';
            }

            if (in_array('paid', $filter_command->status))
            {
                $wh_or[] = 'p.is_paid';
            }

            if (in_array('canceled', $filter_command->status))
            {
                $wh_or[] = 'p.is_canceled';
            }

            if (count($wh_or))
            {
                $sql['status']['where'][] = '(' . implode(' or ', $wh_or) . ')';
            }
        }

        if (isset($filter_command->from_date))
        {
            $sql['from_date'] = $sql_map;

            $from_date_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->from_date . ' UTC'));

            $sql['from_date']['where'][] = 'p.created_at >= ?';
            $sql['from_date']['params'][] = $from_date_immutable;
            $sql['from_date']['types'][] = Types::DATETIME_IMMUTABLE;
        }

        if (isset($filter_command->to_date))
        {
            $sql['to_date'] = $sql_map;

            $to_date_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($filter_command->to_date . ' UTC'));

            $sql['to_date']['where'][] = 'p.created_at <= ?';
            $sql['to_date']['params'][] = $to_date_immutable;
            $sql['to_date']['types'][] = Types::DATETIME_IMMUTABLE;
        }

        $sql['pagination'] = $sql_map;
        $sql['pagination']['params'][] = $params['p']['limit'];
        $sql['pagination']['types'][] = \PDO::PARAM_INT;
        $sql['pagination']['params'][] = $params['p']['start'];
        $sql['pagination']['types'][] = \PDO::PARAM_INT;

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));
        $sql_params = array_merge(...array_column($sql, 'params'));
        $sql_types = array_merge(...array_column($sql, 'types'));

        $payments = [];

        $res = $db->executeQuery('select p.*, r.description,
            u.code, u.name, u.full_name,
            u.activated_at, u.is_active,
            u.is_leaving,
            u.remote_schema, u.remote_email,
            c.value as mail
            from ' . $pp->schema() . '.mollie_payments p
            inner join ' . $pp->schema() . '.mollie_payment_requests r
                on p.request_id = r.id
            inner join ' . $pp->schema() . '.users u
                on p.user_id = u.id
            left join ' . $pp->schema() . '.contact c
                on c.user_id = u.id
                    and c.id_type_contact = (select t.id
                        from ' . $pp->schema() . '.type_contact t
                        where t.abbrev = \'mail\')
            where ' . $sql_where . '
            order by ' . $params['s']['orderby'] . '
            ' . ($params['s']['asc'] ? 'asc' : 'desc') . '
            limit ? offset ?',
            $sql_params, $sql_types);

        while (($row = $res->fetchAssociative()) !== false)
        {
            if (!isset($payments[$row['id']]))
            {
                $payments[$row['id']] = $row;
            }

            if (isset($row['mail']))
            {
                $payments[$row['id']]['has_email'] = true;
            }
        }

        $sql_omit_pagination = $sql;
        unset($sql_omit_pagination['pagination']);
        $sql_omit_pagination_where = implode(' and ', array_merge(...array_column($sql_omit_pagination, 'where')));
        $sql_omit_pagination_params = array_merge(...array_column($sql_omit_pagination, 'params'));
        $sql_omit_pagination_types = array_merge(...array_column($sql_omit_pagination, 'types'));

        $row_count = $db->fetchOne('select count(p.*)
            from ' . $pp->schema() . '.mollie_payments p
            inner join ' . $pp->schema() . '.mollie_payment_requests r
                on p.request_id = r.id
            inner join ' . $pp->schema() . '.users u
                on p.user_id = u.id
            where ' . $sql_omit_pagination_where,
            $sql_omit_pagination_params,
            $sql_omit_pagination_types);

        $count_ary = [
            'open'      => 0,
            'paid'      => 0,
            'canceled'  => 0,
        ];

        $sql_omit_status = $sql_omit_pagination;
        unset($sql_omit_status['status']);
        $sql_omit_status_where = implode(' and ', array_merge(...array_column($sql_omit_status, 'where')));
        $sql_omit_status_params = array_merge(...array_column($sql_omit_status, 'params'));
        $sql_omit_status_types = array_merge(...array_column($sql_omit_status, 'types'));

        $count_ary['open'] = $db->fetchOne('select count(p.*)
            from ' . $pp->schema() . '.mollie_payments p
            inner join ' . $pp->schema() . '.mollie_payment_requests r
                on p.request_id = r.id
            inner join ' . $pp->schema() . '.users u
                on p.user_id = u.id
            where ' . $sql_omit_status_where . '
                and not p.is_paid and not p.is_canceled',
            $sql_omit_status_params,
            $sql_omit_status_types);

        $count_ary['paid'] = $db->fetchOne('select count(p.*)
            from ' . $pp->schema() . '.mollie_payments p
            inner join ' . $pp->schema() . '.mollie_payment_requests r
                on p.request_id = r.id
            inner join ' . $pp->schema() . '.users u
                on p.user_id = u.id
            where ' . $sql_omit_status_where . '
                and p.is_paid',
            $sql_omit_status_params,
            $sql_omit_status_types);

        $count_ary['canceled'] = $db->fetchOne('select count(p.*)
            from ' . $pp->schema() . '.mollie_payments p
            inner join ' . $pp->schema() . '.mollie_payment_requests r
                on p.request_id = r.id
            inner join ' . $pp->schema() . '.users u
                on p.user_id = u.id
            where ' . $sql_omit_status_where . '
                and p.is_canceled',
            $sql_omit_status_params,
            $sql_omit_status_types);

        $asc_preset_ary = [
            'asc'	=> 0,
            'fa' 	=> 'sort',
        ];

        $tableheader_ary = [
            'p.amount' => [
                ...$asc_preset_ary,
                'lbl' => 'Bedrag (EUR)',
            ],
            'r.description' => [
                ...$asc_preset_ary,
                'lbl' 		=> 'Omschrijving',
            ],
            'code' => [
                ...$asc_preset_ary,
                'lbl' => 'Account',
            ],
            'status'	=> [
                ...$asc_preset_ary,
                'lbl' 	=> 'Status',
                'no_sort' => true,
            ],
            'p.created_at' => [
                ...$asc_preset_ary,
                'lbl' 		=> 'Tijdstip',
            ],
            'emails' => [
                ...$asc_preset_ary,
                'lbl' 		=> 'E-mails',
                'title'     => 'Aantal verzonden E-mails',
                'no_sort'   => true,
            ],
        ];

        $tableheader_ary[$params['s']['orderby']]['asc']
            = $params['s']['asc'] ? 0 : 1;
        $tableheader_ary[$params['s']['orderby']]['fa']
            = $params['s']['asc'] ? 'sort-asc' : 'sort-desc';

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!$selected)
            {
                $errors[] = 'Er is geen enkel betaalverzoek geselecteerd.';
            }
        }

        if ($request->isMethod('POST')
            && $bulk_cancel_submit
            && !count($errors))
        {
            if (!$bulk_cancel_verify)
            {
                $errors[] = 'Het nazichtsvakje is niet aangevinkt.';
            }

            $cancel_ary = [];
            $users_cancel_ary = [];

            foreach ($selected as $payment_id => $dummy)
            {
                $payment = $payments[$payment_id];

                if (!$payment['is_paid'] && !$payment['is_canceled'])
                {
                    $cancel_ary[] = (int) $payment_id;
                    $users_cancel_ary[$payment['user_id']] = true;
                }
            }

            if (!count($cancel_ary))
            {
                $errors[] = 'Geen betaalverzoeken geselecteerd die geannuleerd kunnen worden.';
            }

            if (!count($errors))
            {
                $db->executeStatement('update ' . $pp->schema() . '.mollie_payments
                    set canceled_by = ?
                    where id in (?)',
                    [$su->id(), $cancel_ary],
                    [\PDO::PARAM_INT, ArrayParameterType::INTEGER]);

                foreach ($users_cancel_ary as $user_id => $dummy)
                {
                    $cache->delete('users.' . $pp->schema() . '.' . $user_id);
                }

                $success = [];

                switch(count($cancel_ary))
                {
                    case 0:
                        //
                    break;
                    case 1:
                        $success[] = 'Betaalverzoek geannuleerd:';
                    break;
                    default:
                        $success[] = 'Betaalverzoeken geannuleerd:';
                    break;
                }

                foreach($cancel_ary as $payment_id)
                {
                    $payment = $payments[$payment_id];
                    $cancel_str = $account_render->link((int) $payment['user_id'], $pp->ary());
                    $cancel_str .= ', ';
                    $cancel_str .= strtr((string) $payment['amount'], '.', ',') . ' EUR, "';
                    $cancel_str .= htmlspecialchars((string) $payment['description'], ENT_QUOTES);
                    $cancel_str .= '"';
                    $success[] = $cancel_str;
                }

                $alert_service->success($success);

                return $this->redirectToRoute('mollie_payments', $pp->ary());
            }
        }

        if ($request->isMethod('POST')
            && $bulk_mail_submit
            && !count($errors))
        {
            $sent_to_ary = [];
            $not_sent_ary = [];

            if (!$config_service->get_bool('mail.enabled', $pp->schema()))
            {
                $errors[] = 'De E-mail functies zijn niet ingeschakeld. Zie instellingen.';
            }

            if (!$bulk_mail_verify)
            {
                $errors[] = 'Het nazichtsvakje is niet aangevinkt.';
            }

            if (isset($no_mollie_apikey))
            {
                $errors[] = 'Er is geen Mollie Apikey ingesteld.';
            }

            if ($su->is_master())
            {
                $errors[] = 'Het master account kan geen E-mails verzenden.';
            }

            if (!$bulk_mail_subject)
            {
                $errors[] = 'Vul een onderwerp in voor je E-mail.';
            }

            if (!$bulk_mail_content)
            {
                $errors[] = 'De E-mail is leeg.';
            }

            $payments_sent = [];
            $payments_not_sent = [];

            foreach ($selected as $payment_id => $dummy_value)
            {
                if (isset($payments[$payment_id]['has_email']))
                {
                    $sent_to_ary[] = (int) $payments[$payment_id]['user_id'];
                    $payments_sent[] = $payments[$payment_id];
                }
                else
                {
                    $not_sent_ary[] = (int) $payments[$payment_id]['user_id'];
                    $payments_not_sent[] = $payments[$payment_id];
                }
            }

            if (!count($sent_to_ary))
            {
                $errors[] = 'Geen enkele gebruiker van de geselecteerde betaalverzoeken met E-mail adres.';
            }

            if (!count($errors))
            {
                $bulk_mail_content = $html_sanitizer->sanitize($bulk_mail_content);

                $db->insert($pp->schema() . '.emails', [
                    'subject'       => $bulk_mail_subject,
                    'content'       => $bulk_mail_content,
                    'sent_to'       => json_encode($sent_to_ary),
                    'route'         => $request->attributes->get('_route'),
                    'created_by'    => $su->id(),
                ]);

                $email_id = (int) $db->lastInsertId($pp->schema() . '.emails_id_seq');

                foreach($selected as $payment_id => $dummy)
                {
                    $payment = $payments[$payment_id];

                    if (!isset($payment['has_email']))
                    {
                        continue;
                    }

                    $emails_sent = json_decode((string) $payment['emails_sent'], true) ?? [];
                    $emails_sent[] = $email_id;

                    $db->update($pp->schema() . '.mollie_payments', [
                        'emails_sent'   => json_encode($emails_sent),
                    ], ['id' => $payment_id]);

                    $payment_url = $link_render->context_url('mollie_checkout',
                        ['system' => $pp->system()], ['token' => $payment['token']]);
                    $payment_link = '<a href="';
                    $payment_link .= $payment_url;
                    $payment_link .= '">';
                    $payment_link .= $payment_url;
                    $payment_link .= '</a>';

                    $payment['payment_link'] = $payment_link;
                    $payment['amount'] = strtr($payment['amount'], '.', ',');
                    $payment['description'] = $payment['code'] . ' ' . $payment['description'];

                    $vars = [
                        'subject'	    => $bulk_mail_subject,
                        'amount'        => $payment['amount'],
                        'description'   => $payment['description'],
                        'is_paid'       => $payment['is_paid'],
                        'is_canceled'   => $payment['is_canceled'],
                        'token'         => $payment['token'],
                    ];

                    foreach (BulkCnst::MOLLIE_TPL_VARS as $key => $val)
                    {
                        $vars[$key] = $payment[$val];
                    }

                    $bulk_mail_content = strtr($bulk_mail_content, [
                        '{{ betaal_link }}'     => '{{ betaal_link|raw }}'
                    ]);

                    $mail_queue->queue([
                        'schema'			=> $pp->schema(),
                        'to' 				=> $mail_addr_user_service->get((int) $payment['user_id'], $pp->schema()),
                        'pre_html_template' => $bulk_mail_content,
                        'reply_to' 			=> $mail_addr_user_service->get($su->id(), $pp->schema()),
                        'vars'				=> $vars,
                        'template'			=> 'mollie/payment_request',
                    ], random_int(200, 2000));
                }

                $success = [];

                switch(count($sent_to_ary))
                {
                    case 0:
                        //
                    break;
                    case 1:
                        $success[] = 'E-mail verzonden naar:';
                    break;
                    default:
                        $success[] = 'E-mails verzonden naar:';
                    break;
                }

                foreach($sent_to_ary as $user_id)
                {
                    $success[] = $account_render->link($user_id, $pp->ary());
                }

                switch(count($not_sent_ary))
                {
                    case 0:
                    break;
                    case 1:
                        $success[] = 'Wegens ontbreken adres, geen E-mail verzonden naar:';
                    break;
                    default:
                        $success[] = 'Wegens ontbreken adressen, geen E-mails verzonden naar:';
                    break;
                }

                foreach($not_sent_ary as $user_id)
                {
                    $success[] = $account_render->link($user_id, $pp->ary());
                }

                if ($bulk_mail_cc)
                {
                    $vars = [
                        'subject'	        => 'Kopie: ' . $bulk_mail_subject,
                        'payments_sent'     => $payments_sent,
                        'payments_not_sent' => $payments_not_sent,
                        'user_id'           => $su->id(),
                    ];

                    foreach (BulkCnst::MOLLIE_TPL_VARS as $key => $trans)
                    {
                        $vars[$key] = '{{ ' . $key . ' }}';
                    }

                    $mail_queue->queue([
                        'schema'			=> $pp->schema(),
                        'to' 				=> $mail_addr_user_service->get($su->id(), $pp->schema()),
                        'template'			=> 'mollie/payment_request_admin_copy',
                        'pre_html_template'	=> $bulk_mail_content,
                        'vars'				=> $vars,
                    ], 8000);

                    $mail_info = implode('<br />', $success);
                    $mail_info .= '<hr /><br />';

                    $logger->debug('mollie_payments mail:: ' .
                        $mail_info . $bulk_mail_content,
                        ['schema' => $pp->schema()]);
                }

                $alert_service->success($success);

                return $this->redirectToRoute('mollie_payments', $pp->ary());
            }
        }

        if (count($errors))
        {
            $alert_service->error($errors);
        }

        $filtered = isset($filter_command->q)
            || isset($filter_command->user)
            || isset($filter_command->status)
            || isset($filter_command->from_date)
            || isset($filter_command->to_date);

        $filter_collapse = !($filtered || $filter_form_error);

        $out = '<div class="panel panel-info">';

        $out .= '<table class="table table-bordered table-striped ';
        $out .= 'table-hover panel-body footable csv" ';
        $out .= 'data-filter="#combined-filter" data-filter-minimum="1" ';
        $out .= 'data-sort="false">';
        $out .= '<thead>';

        $out .= '<tr>';

        foreach ($tableheader_ary as $key_orderby => $data)
        {
            $out .= '<th';
            $out .= isset($data['title']) ? ' title="' . $data['title'] . '"' : '';
            $out .= '>';

            if (isset($data['no_sort']))
            {
                $out .= $data['lbl'];
            }
            else
            {
                $h_params = $params;

                $h_params['s'] = [
                    'orderby' 	=> $key_orderby,
                    'asc'		=> $data['asc'],
                ];

                $out .= $link_render->link_fa('mollie_payments', $pp->ary(),
                    $h_params, $data['lbl'], [], $data['fa']);
            }

            $out .= '</th>';
        }

        $out .= '</tr>';

        $out .= '</thead>';
        $out .= '<tbody>';

        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

        foreach($payments as $id => $payment)
        {
            $is_remote = isset($payment['remote_schema']) || isset($payment['remote_email']);
            $is_active = $payment['is_active'];
            $is_leaving = $payment['is_leaving'];
            $post_active = isset($payment['activated_at']);
            $is_new = false;
            if ($post_active)
            {
                if ($new_user_treshold->getTimestamp() < strtotime($payment['activated_at'] . ' UTC'))
                {
                    $is_new = true;
                }
            }

            $user_class = null;

            if ($is_active)
            {
                if ($is_remote)
                {
                    $user_class = 'warning';
                }
                else if ($is_leaving && $leaving_users_enabled)
                {
                    $user_class = 'danger';
                }
                else if ($is_new && $new_users_enabled)
                {
                    $user_class = 'success';
                }
            }
            else if ($post_active)
            {
                $user_class = 'inactive';
            }
            else
            {
                $user_class = 'info';
            }

            $out .= '<tr><td>';

            $out .= strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
                '%id%'      => $id,
                '%attr%'    => isset($selected[$id]) ? ' checked' : '',
                '%label%'   => strtr((string) $payment['amount'], '.', ','),
            ]);

            $out .= '</td><td>';

            $out .= $link_render->link('mollie_payments',
                $pp->ary(), [
                    'request_id'    => $payment['request_id'],
                    'f' => [
                        'q' => $payment['description'],
                    ],
                ],
                (string) $payment['description'], []);

            $out .= '</td><td';

            if (isset($user_class))
            {
                $out .= ' class="';
                $out .= $user_class;
                $out .= '"';
            }

            $out .= '>';

            $out .= $account_render->link((int) $payment['user_id'], $pp->ary());

            $out .= '</td><td>';

            $out .= '<span class="label label-lg label-';

            if ($payment['is_canceled'])
            {
                $out .= 'default">geannuleerd';
            }
            else if ($payment['is_paid'])
            {
                $out .= 'success">betaald';
            }
            else
            {
                $out .= 'warning">open';
            }

            $out .= '</span>';

            $out .= '</td><td>';

            $out .= $date_format_service->get((string) $payment['created_at'], 'day', $pp->schema());

            $out .= '</td><td>';

            $td_emails = count(json_decode((string) $payment['emails_sent'], true));

            if (!isset($payment['has_email']))
            {
                $td_emails .= '&nbsp;<span class="label label-lg label-danger" title="Er is geen ';
                $td_emails .= 'E-mail adres ingesteld voor de gebruiker.">';
                $td_emails .= '<i class="fa fa-exclamation-triangle"></i></span>';
            }

            $out .= $td_emails;
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $out .= '</div>';

        $blk = BulkCnst::TPL_SELECT_BUTTONS;

        $blk .= '<h3>Bulk acties met geselecteerde betaalverzoeken</h3>';
        $blk .= '<div class="panel panel-info">';
        $blk .= '<div class="panel-heading">';

        $blk .= '<ul class="nav nav-tabs" role="tablist">';

        $blk .= '<li class="active">';
        $blk .= '<a href="#mail_tab" data-toggle="tab">Mail</a></li>';
        $blk .= '<li>';

        $blk .= '<a href="#cancel_tab" data-toggle="tab">';
        $blk .= 'Annuleren';
        $blk .= '</a>';
        $blk .= '</li>';
        $blk .= '</ul>';

        $blk .= '<div class="tab-content">';

        $blk .= '<div role="tabpanel" class="tab-pane active" id="mail_tab">';

        $blk .= '<form method="post">';

        $blk .= '<h3>E-Mail verzenden</h3>';

        $blk .= '<div class="form-group">';
        $blk .= '<input type="text" class="form-control" ';
        $blk .= 'id="bulk_mail_subject" name="bulk_mail_subject" ';
        $blk .= 'placeholder="Onderwerp" ';
        $blk .= 'value="';
        $blk .= $bulk_mail_subject;
        $blk .= '" required>';
        $blk .= '</div>';

        $blk .= '<div class="form-group">';
        $blk .= '<textarea name="bulk_mail_content" ';
        $blk .= 'class="form-control summernote" ';
        $blk .= 'id="bulk_mail_content" rows="8" ';
        $blk .= 'data-template-vars="';
        $blk .= implode(',', array_keys(BulkCnst::MOLLIE_TPL_VARS));
        $blk .= '" ';
        $blk .= 'required>';
        $blk .= $bulk_mail_content;
        $blk .= '</textarea>';
        $blk .= '<ul><li>Een betaalknop wordt toegevoegd boven je eigen bericht ';
        $blk .= 'bij openstaande betaalverzoeken.';
        $blk .= '</li>';
        $blk .= '<li>Bedrag en omschrijving van betaalverzoeken worden altijd ';
        $blk .= 'bovenaan weergegeven in de verzonden e-mails.</li></ul>';
        $blk .= '</div>';

        $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'bulk_mail_cc',
            '%label%'   => 'Stuur een kopie met verzendinfo naar mijzelf',
            '%attr%'    => $bulk_mail_cc ? ' checked' : '',
        ]);

        $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'bulk_mail_verify',
            '%label%'   => 'Ik heb alles nagekeken.',
            '%attr%'    => ' required',
        ]);

        $blk .= '<input type="submit" value="Verzend" name="bulk_mail_submit" ';
        $blk .= 'class="btn btn-info btn-lg">';

        $blk .= $form_token_service->get_hidden_input();
        $blk .= '</form>';

        $blk .= '</div>';

//--------------------------------------

        $blk .= '<div role="tabpanel" class="tab-pane" ';
        $blk .= 'id="cancel_tab">';

        $blk .= '<form method="post">';

        $blk .= '<h3>Betaalverzoek annuleren</h3>';

        $blk .= '<p>Annuleer geselecteerde ';
        $blk .= '<span class="label label-warning">open</span> ';
        $blk .= 'betaalverzoeken</p>';

        $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'bulk_cancel_verify',
            '%label%'   => 'Ik heb alles nagekeken.',
            '%attr%'    => ' required',
        ]);

        $blk .= '<input type="submit" value="Annuleer" ';
        $blk .= 'name="bulk_cancel_submit" class="btn btn-primary btn-lg">';

        $blk .= $form_token_service->get_hidden_input();
        $blk .= '</form>';

        $blk .= '</div>';

//--------------------------------

        $blk .= '</div>';
        $blk .= '</div>';
        $blk .= '</div>';

        return $this->render('mollie/mollie_payments.html.twig', [
            'data_list_raw'     => $out,
            'bulk_actions_raw'  => $blk,
            'row_count'         => $row_count,
            'filtered'          => $filtered,
            'filter_collapse'   => $filter_collapse,
            'filter_form'       => $filter_form->createView(),
            'count_ary'         => $count_ary,
        ]);
    }
}
