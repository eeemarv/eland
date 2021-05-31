<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Cnst\BulkCnst;
use App\Cnst\StatusCnst;
use App\HtmlProcess\HtmlPurifier;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Render\PaginationRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

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
        PaginationRender $pagination_render,
        BtnTopRender $btn_top_render,
        BtnNavRender $btn_nav_render,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        MenuService $menu_service,
        LinkRender $link_render,
        MailQueue $mail_queue,
        TypeaheadService $typeahead_service,
        MailAddrUserService $mail_addr_user_service,
        DateFormatService $date_format_service,
        PageParamsService $pp,
        SessionUserService $su,
        UserCacheService $user_cache_service,
        HtmlPurifier $html_purifier,
        LoggerInterface $logger,
        AssetsService $assets_service
    ):Response
    {
        if (!$config_service->get_bool('mollie.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Mollie submodule (users) not enabled.');
        }

        $errors = [];

        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        $selected = $request->request->get('sel', []);
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
            'where_or'  => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];
        $sql['common'] = $sql_map;
        $sql['common']['where'][] = '1 = 1';

        $filter_uid = isset($filter['uid']);

        if ($filter_uid)
        {
            $filter['code'] = $account_render->str((int) $filter['uid'], $pp->schema());
            $params['f']['uid'] = $filter['uid'];
        }

        $filter_q = isset($filter['q']) && $filter['q'];

        if ($filter_q)
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][] = 'r.description ilike ?';
            $sql['q']['params'][] = '%' . $filter['q'] . '%';
            $sql['q']['types'][] = \PDO::PARAM_STR;
            $params['f']['q'] = $filter['q'];
        }

        $filter_code = isset($filter['code']) && $filter['code'];

        if ($filter_code)
        {
            [$code] = explode(' ', trim($filter['code']));
            $code = trim($code);

            $uid = $db->fetchOne('select id
                from ' . $pp->schema() . '.users
                where code = ?', [$code], [\PDO::PARAM_STR]);

            $sql['code'] = $sql_map;
            $sql['code']['where'][] = 'u.id = ?';
            $sql['code']['params'][] = $uid ?: 0;
            $sql['code']['types'][] = \PDO::PARAM_INT;

            if ($uid)
            {
                $code = $account_render->str($uid, $pp->schema());
            }

            $params['f']['code'] = $code;
        }

        $filter_status = isset($filter['open'])
                || isset($filter['paid'])
                || isset($filter['canceled']);

        if ($filter_status)
        {
            $sql['status'] = $sql_map;;

            if (isset($filter['open']))
            {
                $sql['status']['where_or'][] = '(p.is_paid = \'f\'::bool and p.is_canceled = \'f\'::bool)';
                $params['f']['open'] = '1';
            }

            if (isset($filter['paid']))
            {
                $sql['status']['where_or'][] = 'p.is_paid = \'t\'::bool';
                $params['f']['paid'] = '1';
            }

            if (isset($filter['canceled']))
            {
                $sql['status']['where_or'][] = 'p.is_canceled = \'t\'::bool';
                $params['f']['canceled'] = 'on';
            }

            if (count($sql['status']['where_or']))
            {
                $sql['status']['where'][] = '(' . implode(' or ', $sql['status']['where_or']) . ')';
            }
        }

        $filter_fdate = isset($filter['fdate']) && $filter['fdate'];

        if ($filter_fdate)
        {
            $sql_fdate = $date_format_service->reverse($filter['fdate'], $pp->schema());

            if ($sql_fdate === '')
            {
                $alert_service->warning('De begindatum is fout geformateerd.');
            }
            else
            {
                $fdate_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($sql_fdate . ' UTC'));
                $sql['fdate'] = $sql_map;
                $sql['fdate']['where'][] = 'p.created_at >= ?';
                $sql['fdate']['params'][] = $fdate_immutable;
                $sql['fdate']['types'][] = Types::DATETIME_IMMUTABLE;
                $params['f']['fdate'] = $fdate = $filter['fdate'];
            }
        }

        $filter_tdate = isset($filter['tdate']) && $filter['tdate'];

        if ($filter_tdate)
        {
            $sql_tdate = $date_format_service->reverse($filter['tdate'], $pp->schema());

            if ($sql_tdate === '')
            {
                $alert_service->warning('De einddatum is fout geformateerd.');
            }
            else
            {
                $tdate_immutable = \DateTimeImmutable::createFromFormat('U', (string) strtotime($sql_tdate . ' UTC'));

                $sql['tdate'] = $sql_map;
                $sql['tdate']['where'][] = 'p.created_at <= ?';
                $sql['tdate']['params'][] = $tdate_immutable;
                $sql['tdate']['types'][] = Types::DATETIME_IMMUTABLE;
                $params['f']['tdate'] = $tdate = $filter['tdate'];
            }
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

        $stmt = $db->executeQuery('select p.*, r.description,
            u.code, u.name, u.full_name,
            u.status, u.adate,
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

        while (($row = $stmt->fetch()) !== false)
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
                and p.is_paid = \'f\'::bool and p.is_canceled = \'f\'::bool',
            $sql_omit_status_params,
            $sql_omit_status_types);

        $count_ary['paid'] = $db->fetchOne('select count(p.*)
            from ' . $pp->schema() . '.mollie_payments p
            inner join ' . $pp->schema() . '.mollie_payment_requests r
                on p.request_id = r.id
            inner join ' . $pp->schema() . '.users u
                on p.user_id = u.id
            where ' . $sql_omit_status_where . '
                and p.is_paid = \'t\'::bool',
            $sql_omit_status_params,
            $sql_omit_status_types);

        $count_ary['canceled'] = $db->fetchOne('select count(p.*)
            from ' . $pp->schema() . '.mollie_payments p
            inner join ' . $pp->schema() . '.mollie_payment_requests r
                on p.request_id = r.id
            inner join ' . $pp->schema() . '.users u
                on p.user_id = u.id
            where ' . $sql_omit_status_where . '
                and p.is_canceled = \'t\'::bool',
            $sql_omit_status_params,
            $sql_omit_status_types);

        $pagination_render->init('mollie_payments', $pp->ary(),
            $row_count, $params);

        $asc_preset_ary = [
            'asc'	=> 0,
            'fa' 	=> 'sort',
        ];

        $tableheader_ary = [
            'p.amount' => array_merge($asc_preset_ary, [
                'lbl' => 'Bedrag (EUR)',
            ]),
            'r.description' => array_merge($asc_preset_ary, [
                'lbl' 		=> 'Omschrijving',
            ]),
            'code' => array_merge($asc_preset_ary, [
                'lbl' => 'Account',
            ]),
            'status'	=> array_merge($asc_preset_ary, [
                'lbl' 	=> 'Status',
                'no_sort' => true,
            ]),
            'p.created_at' => array_merge($asc_preset_ary, [
                'lbl' 		=> 'Tijdstip',
            ]),
            'emails' => array_merge($asc_preset_ary, [
                'lbl' 		=> 'E-mails',
                'title'     => 'Aantal verzonden E-mails',
                'no_sort'   => true,
            ]),
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
                    set canceled_by = ? where id in (?)',
                    [$su->id(), $cancel_ary],
                    [\PDO::PARAM_INT, Db::PARAM_INT_ARRAY]);

                foreach ($users_cancel_ary as $user_id => $dummy)
                {
                    $user_cache_service->clear((int) $user_id, $pp->schema());
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
                    $cancel_str = $account_render->link($payment['user_id'], $pp->ary());
                    $cancel_str .= ', ';
                    $cancel_str .= strtr($payment['amount'], '.', ',') . ' EUR, "';
                    $cancel_str .= htmlspecialchars($payment['description'], ENT_QUOTES);
                    $cancel_str .= '"';
                    $success[] = $cancel_str;
                }

                $alert_service->success($success);
                $link_render->redirect('mollie_payments', $pp->ary(), []);
            }
        }

        if ($request->isMethod('POST')
            && $bulk_mail_submit
            && !count($errors))
        {
            $sent_to_ary = [];
            $not_sent_ary = [];

            if (!$config_service->get('mailenabled', $pp->schema()))
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
                $bulk_mail_content = $html_purifier->purify($bulk_mail_content);

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

                    $emails_sent = json_decode($payment['emails_sent'], true) ?? [];
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
                $link_render->redirect('mollie_payments', $pp->ary(), []);
            }
        }

        if (count($errors))
        {
            $alert_service->error($errors);
        }

        $assets_service->add([
            'codemirror',
            'summernote',
            'summernote_email.js',
            'datepicker',
            'table_sel.js',
        ]);

        $btn_top_render->create('mollie_payments_add', $pp->ary(),
            [], 'Betaalverzoeken aanmaken');

        $btn_top_render->config('mollie_config', $pp->ary(),
            [], 'Mollie configuratie');

        $btn_nav_render->csv();

        $filtered = !$filter_uid && (
            $filter_q
            || $filter_code
            || $filter_status
            || $filter_fdate
            || $filter_tdate
        );

        $out = '<div class="panel panel-info';
        $out .= $filtered ? '' : ' collapse';
        $out .= '" id="filter">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get" class="form-horizontal">';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="q" value="';
        $out .= $filter['q'] ?? '';
        $out .= '" name="f[q]" placeholder="Omschrijving">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="code_addon">';
        $out .= '<span class="fa fa-user"></span></span>';

        $out .= '<input type="text" class="form-control" ';
        $out .= 'aria-describedby="code_addon" ';

        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini($pp->ary())
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'extern'])
            ->add('accounts', ['status' => 'inactive'])
            ->add('accounts', ['status' => 'ip'])
            ->add('accounts', ['status' => 'im'])
            ->str([
                'filter'		=> 'accounts',
                'newuserdays'	=> $config_service->get('newuserdays', $pp->schema()),
            ]);

        $out .= '" ';

        $out .= 'name="f[code]" id="code" placeholder="Account Code" ';
        $out .= 'value="';
        $out .= $code ?? '';
        $out .= '">';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<div class="col-md-12">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<div class="custom-checkbox">';

        foreach (self::STATUS_RENDER as $key => $render)
        {
            $name = 'f[' . $key . ']';

            $attr = isset($filter[$key]) ? ' checked' : '';

            $label = '<span class="btn btn-' . $render['class'] . '">';
            $label .= $render['label'];
            $label .= '&nbsp;(' . $count_ary[$key] . ')';
            $label .= '</span>';

			$out .= strtr(BulkCnst::TPL_CHECKBOX_INLINE, [
				'%name%'	=> $name,
				'%attr%'	=> $attr,
				'%label%'	=> $label,
			]);
        }

        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="fdate_addon">Vanaf ';
        $out .= '<span class="fa fa-calendar"></span></span>';
        $out .= '<input type="text" class="form-control margin-bottom" ';
        $out .= 'aria-describedby="fdate_addon" ';

        $out .= 'id="fdate" name="f[fdate]" ';
        $out .= 'value="';
        $out .= $fdate ?? '';
        $out .= '" ';
        $out .= 'data-provide="datepicker" ';
        $out .= 'data-date-format="';
        $out .= $date_format_service->datepicker_format($pp->schema());
        $out .= '" ';
        $out .= 'data-date-default-view-date="-1y" ';
        $out .= 'data-date-end-date="0d" ';
        $out .= 'data-date-language="nl" ';
        $out .= 'data-date-today-highlight="true" ';
        $out .= 'data-date-autoclose="true" ';
        $out .= 'data-date-immediate-updates="true" ';
        $out .= 'data-date-orientation="bottom" ';
        $out .= 'placeholder="';
        $out .= $date_format_service->datepicker_placeholder($pp->schema());
        $out .= '">';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="tdate_addon">Tot ';
        $out .= '<span class="fa fa-calendar"></span></span>';
        $out .= '<input type="text" class="form-control margin-bottom" ';
        $out .= 'aria-describedby="tdate_addon" ';

        $out .= 'id="tdate" name="f[tdate]" ';
        $out .= 'value="';
        $out .= $tdate ?? '';
        $out .= '" ';
        $out .= 'data-provide="datepicker" ';
        $out .= 'data-date-format="';
        $out .= $date_format_service->datepicker_format($pp->schema());
        $out .= '" ';
        $out .= 'data-date-end-date="0d" ';
        $out .= 'data-date-language="nl" ';
        $out .= 'data-date-today-highlight="true" ';
        $out .= 'data-date-autoclose="true" ';
        $out .= 'data-date-immediate-updates="true" ';
        $out .= 'data-date-orientation="bottom" ';
        $out .= 'placeholder="';
        $out .= $date_format_service->datepicker_placeholder($pp->schema());
        $out .= '">';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-2">';
        $out .= '<input type="submit" value="Toon" ';
        $out .= 'class="btn btn-default btn-block">';
        $out .= '</div>';

        $out .= '</div>';

        $params_form = array_merge($params, $pp->ary());
        unset($params_form['role_short']);
        unset($params_form['system']);
        unset($params_form['f']);
        unset($params_form['p']['start']);

        $params_form = http_build_query($params_form, 'prefix', '&');
        $params_form = urldecode($params_form);
        $params_form = explode('&', $params_form);

        foreach ($params_form as $param)
        {
            [$name, $value] = explode('=', $param);

            if (!isset($value) || $value === '')
            {
                continue;
            }

            $out .= '<input name="' . $name . '" ';
            $out .= 'value="' . $value . '" type="hidden">';
        }

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= $pagination_render->get();

        $out .= '<div class="panel panel-info">';

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
            $user_status = $payment['status'];

            if (isset($payment['adate'])
                && $payment['status'] === 1
                && $new_user_treshold->getTimestamp() < strtotime($payment['adate'] . ' UTC'))
            {
                $user_status = 3;
            }

            $out .= '<tr><td>';

            $out .= strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
                '%id%'      => $id,
                '%attr%'    => isset($selected[$id]) ? ' checked' : '',
                '%label%'   => strtr($payment['amount'], '.', ','),
            ]);

            $out .= '</td><td>';

            $out .= $link_render->link('mollie_payments',
                $pp->ary(), [
                    'request_id'    => $payment['request_id'],
                    'f' => [
                        'q' => $payment['description'],
                    ],
                ],
                $payment['description'], []);

            $out .= '</td><td';

            if (isset(StatusCnst::CLASS_ARY[$user_status]))
            {
                $out .= ' class="';
                $out .= StatusCnst::CLASS_ARY[$user_status];
                $out .= '"';
            }

            $out .= '>';

            $out .= $account_render->link($payment['user_id'], $pp->ary());

            $out .= '</td><td>';

            $out .= '<span class="label label-';

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

            $out .= $date_format_service->get($payment['created_at'], 'day', $pp->schema());

            $out .= '</td><td>';

            $td_emails = count(json_decode($payment['emails_sent'], true));

            if (!isset($payment['has_email']))
            {
                $td_emails .= '&nbsp;<span class="label label-danger" title="Er is geen ';
                $td_emails .= 'E-mail adres ingesteld voor de gebruiker.">';
                $td_emails .= '<i class="fa fa-exclamation-triangle"></i></span>';
            }

            $out .= $td_emails;
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $out .= '</div>';

        $out .= $pagination_render->get();

        $out .= BulkCnst::TPL_SELECT_BUTTONS;

        $out .= '<h3>Bulk acties met geselecteerde betaalverzoeken</h3>';
        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<ul class="nav nav-tabs" role="tablist">';

        $out .= '<li class="active">';
        $out .= '<a href="#mail_tab" data-toggle="tab">Mail</a></li>';
        $out .= '<li>';

        $out .= '<a href="#cancel_tab" data-toggle="tab">';
        $out .= 'Annuleren';
        $out .= '</a>';
        $out .= '</li>';
        $out .= '</ul>';

        $out .= '<div class="tab-content">';

        $out .= '<div role="tabpanel" class="tab-pane active" id="mail_tab">';

        $out .= '<form method="post">';

        $out .= '<h3>E-Mail verzenden</h3>';

        $out .= '<div class="form-group">';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="bulk_mail_subject" name="bulk_mail_subject" ';
        $out .= 'placeholder="Onderwerp" ';
        $out .= 'value="';
        $out .= $bulk_mail_subject;
        $out .= '" required>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<textarea name="bulk_mail_content" ';
        $out .= 'class="form-control summernote" ';
        $out .= 'id="bulk_mail_content" rows="8" ';
        $out .= 'data-template-vars="';
        $out .= implode(',', array_keys(BulkCnst::MOLLIE_TPL_VARS));
        $out .= '" ';
        $out .= 'required>';
        $out .= $bulk_mail_content;
        $out .= '</textarea>';
        $out .= '<ul><li>Een betaalknop wordt toegevoegd boven je eigen bericht ';
        $out .= 'bij openstaande betaalverzoeken.';
        $out .= '</li>';
        $out .= '<li>Bedrag en omschrijving van betaalverzoeken worden altijd ';
        $out .= 'bovenaan weergegeven in de verzonden e-mails.</li></ul>';
        $out .= '</div>';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'bulk_mail_cc',
            '%label%'   => 'Stuur een kopie met verzendinfo naar mijzelf',
            '%attr%'    => $bulk_mail_cc ? ' checked' : '',
        ]);

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'bulk_mail_verify',
            '%label%'   => 'Ik heb alles nagekeken.',
            '%attr%'    => ' required',
        ]);

        $out .= '<input type="submit" value="Verzend" name="bulk_mail_submit" ';
        $out .= 'class="btn btn-info btn-lg">';

        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';

//--------------------------------------

        $out .= '<div role="tabpanel" class="tab-pane" ';
        $out .= 'id="cancel_tab">';

        $out .= '<form method="post">';

        $out .= '<h3>Betaalverzoek annuleren</h3>';

        $out .= '<p>Annuleer geselecteerde ';
        $out .= '<span class="label label-warning">open</span> ';
        $out .= 'betaalverzoeken</p>';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'bulk_cancel_verify',
            '%label%'   => 'Ik heb alles nagekeken.',
            '%attr%'    => ' required',
        ]);

        $out .= '<input type="submit" value="Annuleer" ';
        $out .= 'name="bulk_cancel_submit" class="btn btn-primary btn-lg">';

        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';

//--------------------------------

        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('mollie_payments');

        return $this->render('mollie/mollie_payments.html.twig', [
            'content'   => $out,
            'filtered'  => $filtered,
            'schema'    => $pp->schema(),
        ]);
    }
}
