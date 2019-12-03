<?php declare(strict_types=1);

namespace App\Controller;

use App\Cnst\StatusCnst;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\PaginationRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\AutoMinLimitService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TransactionService;
use App\Service\TypeaheadService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class MolliePaymentsController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
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
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        DateFormatService $date_format_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        AssetsService $assets_service
    ):Response
    {
        $errors = [];

        $where_sql = $params_sql = [];

        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        $params = [
            's'	=> [
                'orderby'	=> $sort['orderby'] ?? 'created_at',
                'asc'		=> $sort['asc'] ?? 0,
            ],
            'p'	=> [
                'start'		=> $pag['start'] ?? 0,
                'limit'		=> $pag['limit'] ?? 100,
            ],
        ];



        if (count($where_sql))
        {
            $where_sql = ' and ' . implode(' and ', $where_sql);
        }
        else
        {
            $where_sql = '';
        }

        $payments = [];

        $rs = $db->prepare('select p.*, r.description,
            u.letscode, u.name, u.status, u.adate
            from ' . $pp->schema() . '.mollie_payments p,
                ' . $pp->schema() . '.mollie_payment_requests r,
                ' . $pp->schema() . '.users u
            where p.request_id = r.id
                and p.user_id = u.id
                ' . $where_sql . '
            order by p.' . $params['s']['orderby'] . '
            ' . ($params['s']['asc'] ? 'asc' : 'desc') . '
            limit ' . $params['p']['limit'] . '
            offset ' . $params['p']['start']);

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $payments[$row['id']] = $row;
        }

        $row = $db->fetchAssoc('select count(p.*), sum(p.amount)
            from ' . $pp->schema() . '.mollie_payments p ' .
            $where_sql, $params_sql);

        $row_count = $row['count'];
        $amount_sum = $row['sum'];

        $pagination_render->init('mollie_payments', $pp->ary(),
            $row_count, $params);

        if ($request->isMethod('POST'))
        {

        }
        else
        {

        }

        $assets_service->add([
            'datepicker',
        ]);

        $btn_top_render->create('mollie_payments_add', $pp->ary(),
            [], 'Betaalverzoek aanmaken');

        $btn_top_render->config('mollie_config', $pp->ary(),
            [], 'Mollie configuratie');

        $btn_nav_render->csv();

        $filtered = !isset($filter['uid']) && (
            (isset($filter['code']) && $filter['code'] !== '')
            || (isset($filter['fdate']) && $filter['fdate'] !== '')
            || (isset($filter['tdate']) && $filter['tdate'] !== ''));

        $heading_render->add('Mollie betaalverzoeken');
        $heading_render->fa('eur');
        $heading_render->add_filtered($filtered);
        $heading_render->btn_filter();

        $out = '<div class="panel panel-info';
        $out .= $filtered ? '' : ' collapse';
        $out .= '" id="filter">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get" class="form-horizontal">';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-12">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="q" value="';
        $out .= $filter['q'] ?? '';
        $out .= '" name="f[q]" placeholder="Zoekterm">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="code_addon">Van ';
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

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="tcode_addon">Naar ';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control margin-bottom" ';
        $out .= 'data-typeahead-source="code" ';
        $out .= 'placeholder="Account Code" ';
        $out .= 'aria-describedby="code_addon" ';
        $out .= 'name="f[code]" value="';
        $out .= $code ?? '';
        $out .= '">';
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

        $out = $pagination_render->get();

        $out .= '<div class="panel panel-info">';

        $out .= '<table class="table table-bordered table-striped ';
        $out .= 'table-hover panel-body footable" ';
        $out .= 'data-filter="#combined-filter" data-filter-minimum="1">';
        $out .= '<thead>';

        $out .= '<tr>';
        $out .= '<th>Account</th>';
        $out .= '<th>Bedrag (EUR)</th>';
        $out .= '<th>Status</th>';
        $out .= '<th>Omschrijving</th>';
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
            $td[] = $account_render->link($payment['user_id'], $pp->ary());
            $td[] = $payment['amount'];
            $td[] = $link_render->link('mollie_payment_requests',
                $pp->ary(), ['id' => $payment['request_id']],
                $payment['description'], []);

            $out .= '<tr';



            $out .= '><td>';
            $out .= implode('</td><td>', $td);
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $out .= '</div>';

        $out .= $pagination_render->get();

/*
        $out .= '<div class="panel-heading">';


        $out .= '<div class="form-group">';
        $out .= '<label for="mail_en" class="control-label">';
        $out .= '<input type="checkbox" id="mail_en" name="mail_en" value="1"';
        $out .= $mail_en ? ' checked="checked"' : '';
        $out .= '>';
        $out .= ' Verstuur notificatie mails</label>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label>';
        $out .= '<input type="checkbox" name="verify" ';
        $out .= 'value="1" required> ';
        $out .= 'Ik heb nagekeken dat de juiste ';
        $out .= 'bedragen en de juiste "Van" of "Aan" ';
        $out .= 'Account Code ingevuld zijn.';
        $out .= '</label>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('transactions', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Massa transactie uitvoeren" ';
        $out .= 'name="zend" class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<input type="hidden" value="';
        $out .= $transid;
        $out .= '" name="transid">';

        $out .= '</form>';
*/

        $menu_service->set('mollie_payments');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
