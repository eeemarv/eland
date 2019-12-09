<?php declare(strict_types=1);

namespace App\Controller;

use App\Cnst\BulkCnst;
use App\Cnst\StatusCnst;
use App\HtmlProcess\HtmlPurifier;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
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
use Psr\Log\LoggerInterface;

class MolliePaymentsController extends AbstractController
{
    const STATUS_RENDER = [
        'open'      => [
            'label'     => 'open',
            'class'     => 'warning',
        ],
        'payed'     => [
            'label'     => 'betaald',
            'class'     => 'success',
        ],
        'canceled'  => [
            'label'     => 'geannuleerd',
            'class'     => 'default',
        ],
    ];

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
        HeadingRender $heading_render,
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

//----------

        $mollie_apikey = $db->fetchColumn('select data->>\'apikey\'
            from ' . $pp->schema() . '.config
            where id = \'mollie\'');

        if (!$mollie_apikey ||
            !(strpos($mollie_apikey, 'test_') === 0
            || strpos($mollie_apikey, 'live_') === 0))
        {
            if ($request->isMethod('GET'))
            {
                $alert_service->warning('Je kan geen betaalverzoeken aanmaken want
                    er is geen Mollie apikey ingesteld in de ' .
                    $link_render->link('mollie_config', $pp->ary(), [], 'configuratie', []));
            }

            $no_mollie_apikey = true;
        }
        else if (strpos($mollie_apikey, 'live_') !== 0)
        {
            if ($request->isMethod('GET'))
            {
                $alert_service->warning('Er is geen <code>live_</code> Mollie apikey ingsteld in de ' .
                    $link_render->link('mollie_config', $pp->ary(), [], 'configuratie', []) .
                    '. Betalingen kunnen niet uitgevoerd worden!');
            }
        }

//-------------

        $where_sql = [];
        $params_sql = [];

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

        if (isset($filter['uid']))
        {
            $filter['code'] = $account_render->str($filter['uid'], $pp->schema());
            $params['f']['uid'] = $filter['uid'];
        }

        if (isset($filter['q']) && $filter['q'])
        {
            $where_sql[] = 'r.description ilike ?';
            $params_sql[] = '%' . $filter['q'] . '%';
            $params['f']['q'] = $filter['q'];
        }

        if (isset($filter['code']) && $filter['code'])
        {
            [$code] = explode(' ', trim($filter['code']));
            $code = trim($code);

            $uid = $db->fetchColumn('select id
                from ' . $pp->schema() . '.users
                where letscode = ?', [$code]);

            $where_sql[] = 'u.id = ?';
            $params_sql[] = $uid ?: 0;

            if ($uid)
            {
                $code = $account_render->str($uid, $pp->schema());
            }

            $params['f']['code'] = $code;
        }

        $filter_status = isset($filter['status']) &&
            !(isset($filter['status']['open'])
                && isset($filter['status']['payed'])
                && isset($filter['status']['canceled']));

        if ($filter_status)
        {
            $where_status_sql = [];

            if (isset($filter['status']['open']))
            {
                $where_status_sql[] = '(p.is_payed = \'f\'::bool and p.is_canceled = \'f\'::bool)';
                $params['f']['status']['open'] = 'on';
            }

            if (isset($filter['status']['payed']))
            {
                $where_status_sql[] = 'p.is_payed = \'t\'::bool';
                $params['f']['status']['payed'] = 'on';
            }

            if (isset($filter['status']['canceled']))
            {
                $where_status_sql[] = 'p.is_canceled = \'t\'::bool';
                $params['f']['status']['canceled'] = 'on';
            }

            if (count($where_status_sql))
            {
                $where_sql[] = '(' . implode(' or ', $where_status_sql) . ')';
            }
        }

        if (isset($filter['fdate']) && $filter['fdate'])
        {
            $fdate_sql = $date_format_service->reverse($filter['fdate'], $pp->schema());

            if ($fdate_sql === '')
            {
                $alert_service->warning('De begindatum is fout geformateerd.');
            }
            else
            {
                $where_sql[] = 'p.created_at >= ?';
                $params_sql[] = $fdate_sql;
                $params['f']['fdate'] = $fdate = $filter['fdate'];
            }
        }

        if (isset($filter['tdate']) && $filter['tdate'])
        {
            $tdate_sql = $date_format_service->reverse($filter['tdate'], $pp->schema());

            if ($tdate_sql === '')
            {
                $alert_service->warning('De einddatum is fout geformateerd.');
            }
            else
            {
                $where_sql[] = 'p.created_at <= ?';
                $params_sql[] = $tdate_sql;
                $params['f']['tdate'] = $tdate = $filter['tdate'];
            }
        }

        if (count($where_sql))
        {
            $where_sql = ' and ' . implode(' and ', $where_sql);
        }
        else
        {
            $where_sql = '';
        }

        $payments = [];

        $rs = $db->executeQuery('select p.*, r.description,
            u.letscode as code, u.name, u.status, u.adate,
            c.value as mail
            from ' . $pp->schema() . '.mollie_payments p,
                ' . $pp->schema() . '.mollie_payment_requests r,
                ' . $pp->schema() . '.users u
            left join ' . $pp->schema() . '.contact c
                on c.id_user = u.id
                    and c.id_type_contact = (select t.id
                        from ' . $pp->schema() . '.type_contact t
                        where t.abbrev = \'mail\')
            where p.request_id = r.id
                and p.user_id = u.id
                ' . $where_sql . '
            order by ' . $params['s']['orderby'] . '
            ' . ($params['s']['asc'] ? 'asc' : 'desc') . '
            limit ' . $params['p']['limit'] . '
            offset ' . $params['p']['start'],
        $params_sql);

        while ($row = $rs->fetch())
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

        $row = $db->fetchAssoc('select count(p.*), sum(p.amount)
            from ' . $pp->schema() . '.mollie_payments p,
                ' . $pp->schema() . '.mollie_payment_requests r,
                ' . $pp->schema() . '.users u
            where p.request_id = r.id
                and p.user_id = u.id
                ' . $where_sql, $params_sql);

        $row_count = $row['count'];
        $amount_sum = $row['sum'];

        $pagination_render->init('mollie_payments', $pp->ary(),
            $row_count, $params);

        $asc_preset_ary = [
            'asc'	=> 0,
            'fa' 	=> 'sort',
        ];

        $tableheader_ary = [
            'code' => array_merge($asc_preset_ary, [
                'lbl' => 'Account',
            ]),
            'p.amount' => array_merge($asc_preset_ary, [
                'lbl' => 'Bedrag (EUR)',
            ]),
            'status'	=> array_merge($asc_preset_ary, [
                'lbl' 	=> 'Status',
                'no_sort' => true,
            ]),
            'r.description' => array_merge($asc_preset_ary, [
                'lbl' 		=> 'Omschrijving',
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

                if (!$payment['is_payed'] && !$payment['is_canceled'])
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
                $db->executeUpdate('update ' . $pp->schema() . '.mollie_payments
                    set canceled_by = ? where id in (?)',
                    [$su->id(), $cancel_ary],
                    [\PDO::PARAM_INT, Db::PARAM_INT_ARRAY]);

                $db->executeUpdate('update ' . $pp->schema() . '.users u
                    set has_open_mollie_payment = \'f\'::bool
                    where u.id not in (select p.user_id
                        from ' . $pp->schema() . '.mollie_payments p
                        where p.is_canceled = \'f\'::bool
                            and p.is_payed = \'f\'::bool)');

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

            foreach ($selected as $payment_id => $dummy_value)
            {
                if (isset($payments[$payment_id]['has_email']))
                {
                    $sent_to_ary[] = (int) $payments[$payment_id]['user_id'];
                }
                else
                {
                    $not_sent_ary[] = (int) $payments[$payment_id]['user_id'];
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

                    $payment_url = $link_render->context_url('mollie_checkout_anonymous',
                        ['system' => $pp->system()], ['token' => $payment['token']]);
                    $payment_link = '<a href="';
                    $payment_link .= $payment_url;
                    $payment_link .= '">';
                    $payment_link .= $payment_url;
                    $payment_link .= '</a>';

                    $payment['payment_link'] = $payment_link;
                    $payment['amount'] = strtr($payment['amount'], '.', ',');

                    $vars = [
                        'subject'	=> $bulk_mail_subject,
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
                        'template'			=> 'skeleton',
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
                        'subject'	=> 'Kopie: ' . $bulk_mail_subject,
                    ];

                    foreach (BulkCnst::MOLLIE_TPL_VARS as $key => $trans)
                    {
                        $vars[$key] = '{{ ' . $key . ' }}';
                    }

                    $mail_info = implode('<br />', $success);
                    $mail_info .= '<hr /><br />';

                    $mail_queue->queue([
                        'schema'			=> $pp->schema(),
                        'to' 				=> $mail_addr_user_service->get($su->id(), $pp->schema()),
                        'template'			=> 'skeleton',
                        'pre_html_template'	=> $mail_info . $bulk_mail_content,
                        'vars'				=> $vars,
                    ], 8000);

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

        $filtered = !isset($filter['uid']) && (
            (isset($filter['q']) && $filter['q'] !== '')
            || (isset($filter['code']) && $filter['code'] !== '')
            || isset($filter['status'])
            || (isset($filter['fdate']) && $filter['fdate'] !== '')
            || (isset($filter['tdate']) && $filter['tdate'] !== ''));

        $heading_render->add('Mollie betaalverzoeken');
        $heading_render->fa('eur');
        $heading_render->add_filtered($filtered);
        $heading_render->btn_filter();

//------------------

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

        foreach (self::STATUS_RENDER as $key => $render)
        {
            $name = 'f[status][' . $key . ']';
            $out .= '<label class="checkbox-inline" for="' . $name . '">';
            $out .= '<input type="checkbox" id="' . $name . '" ';
            $out .= 'name="' . $name . '"';
            $out .= isset($filter['status'][$key]) ? ' checked' : '';
            $out .= '>&nbsp;';
            $out .= '<span class="label label-' . $render['class'] . '">';
            $out .= $render['label'];
            $out .= '</span>';
            $out .= '</label>';
        }

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
        unset($params_form['uid']);
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

//---------------------------------

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
                && $new_user_treshold < strtotime($payment['adate']))
            {
                $user_status = 3;
            }

            $out .= '<tr><td';

            if (isset(StatusCnst::CLASS_ARY[$user_status]))
            {
                $out .= ' class="';
                $out .= StatusCnst::CLASS_ARY[$user_status];
                $out .= '"';
            }

            $out .= '>';

            $td = [];

            $account_str = strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
                '%id%'      => $id,
                '%attr%'    => isset($selected[$id]) ? ' checked' : '',
                '%label%'   => ' ',
            ]);

            $account_str .= $account_render->link($payment['user_id'], $pp->ary());

            $td[] = $account_str;
            $td[] = strtr($payment['amount'], '.', ',');

            $status_label = '<span class="label label-';

            if ($payment['is_canceled'])
            {
                $status_label .= 'default">geannuleerd';
            }
            else if ($payment['is_payed'])
            {
                $status_label .= 'success">betaald';
            }
            else
            {
                $status_label .= 'warning">open';
            }

            $td[] = $status_label . '</span>';

            $td[] = $link_render->link('mollie_payments',
                $pp->ary(), [
                    'request_id'    => $payment['request_id'],
                    'f' => [
                        'q' => $payment['description'],
                    ],
                ],
                $payment['description'], []);

            $td[] = $date_format_service->get($payment['created_at'], 'day', $pp->schema());

            $td_emails = count(json_decode($payment['emails_sent'], true));

            if (!isset($payment['has_email']))
            {
                $td_emails .= '&nbsp;<span class="label label-danger" title="Er is geen ';
                $td_emails .= 'E-mail adres ingesteld voor de gebruiker.">';
                $td_emails .= '<i class="fa fa-exclamation-triangle"></i></span>';
            }

            $td[] = $td_emails;

            $out .= implode('</td><td>', $td);
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
//-----------------------

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

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function get_checkbox_filter(
        array $checkbox_ary,
        string $filter_id,
        array $filter_ary
    ):string
    {
        $out = '';

        foreach ($checkbox_ary as $key => $label)
        {
            $id = 'f_' . $filter_id . '_' . $key;
            $out .= '<label class="checkbox-inline" for="' . $id . '">';
            $out .= '<input type="checkbox" id="' . $id . '" ';
            $out .= 'name="f[' . $filter_id . '][' . $key . ']"';
            $out .= isset($filter_ary[$filter_id][$key]) ? ' checked' : '';
            $out .= '>&nbsp;';
            $out .= '<span class="btn btn-default">';
            $out .= $label;
            $out .= '</span>';
            $out .= '</label>';
        }

        return $out;
    }
}
