<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Cnst\BulkCnst;
use App\Cnst\StatusCnst;
use App\Controller\Users\UsersListController;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TokenGeneratorService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MolliePaymentsAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/mollie/payments/add/{status}',
        name: 'mollie_payments_add',
        methods: ['GET', 'POST'],
        requirements: [
            'status'        => '%assert.account_status%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'status'        => 'active',
            'module'        => 'users',
            'sub_module'    => 'mollie',
        ],
    )]

    public function __invoke(
        Request $request,
        string $status,
        Db $db,
        AlertService $alert_service,
        UserCacheService $user_cache_service,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        AccountRender $account_render,
        DateFormatService $date_format_service,
        PageParamsService $pp,
        SessionUserService $su,
        TokenGeneratorService $token_generator_service
    ):Response
    {
        if (!$config_service->get_bool('mollie.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Mollie submodule (users) not enabled.');
        }

        $errors = [];
        $params = [];

        $q = $request->get('q', '');
        $amount = $request->request->all('amount', []);
        $description = trim($request->request->get('description', ''));
        $verify = $request->request->get('verify');

        $mollie_apikey = $config_service->get_str('mollie.apikey', $pp->schema());
        $new_users_enabled = $config_service->get_bool('users.new.enabled', $pp->schema());
        $leaving_users_enabled = $config_service->get_bool('users.leaving.enabled', $pp->schema());

        if (!$mollie_apikey ||
            !(str_starts_with($mollie_apikey, 'test_')
            || str_starts_with($mollie_apikey, 'live_')))
        {
            if ($request->isMethod('GET'))
            {
                $alert_service->warning('Je kan geen betaalverzoeken aanmaken want
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

        $status_def_ary = UsersListController::get_status_def_ary($config_service, $item_access_service, $pp);

        $sql_map = [
            'where'     => [],
            'where_or'  => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];
        $sql['common'] = $sql_map;
        $sql['common']['where'][] = '1 = 1';

        $sql['status'] = $sql_map;

        foreach ($status_def_ary[$status]['sql'] as $st_def_key => $def_sql_ary)
        {
            foreach ($def_sql_ary as $def_val)
            {
                $sql['status'][$st_def_key][] = $def_val;
            }
        }

        $params['status'] = $status;

        $users = [];

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));
        $sql_params = array_merge(...array_column($sql, 'params'));
        $sql_types = array_merge(...array_column($sql, 'types'));

        $res = $db->executeQuery('select u.id,
                u.name, u.full_name, u.code,
                u.role, u.status, u.adate,
                p1.is_paid, p1.is_canceled,
                p1.created_at as last_created_at,
                p1.amount, p1.description
            from ' . $pp->schema() . '.users u
            left join lateral (select p.*, r.description
                from ' . $pp->schema() . '.mollie_payments p,
                    ' . $pp->schema() . '.mollie_payment_requests r
                where p.user_id = u.id
                    and r.id = p.request_id
                order by p.created_at desc
                limit 1) p1
            on \'t\'::bool
            where ' . $sql_where . '
            order by u.code asc', $sql_params, $sql_types);

        while(($row = $res->fetchAssociative()) !== false)
        {
            $users[$row['id']] = $row;
        }

        if ($request->isMethod('POST'))
        {
            $user_id_ary = [];

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (isset($no_mollie_apikey))
            {
                $errors[] = 'Er is geen Mollie apikey geconfigureerd.';
            }

            if (!$verify)
            {
                $errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
            }

            if (!$description)
            {
                $errors[] = 'Vul een omschrijving in.';
            }

            $count_amounts = 0;

            foreach ($amount as $user_id => $amo)
            {
                if (!isset($users[$user_id]['code'])
                    || $users[$user_id]['code'] === '')
                {
                    $errors[] = 'Er is geen Account Code ingesteld voor gebruiker met naam ' . $users[$user_id]['name'];
                }

                if (!$amo)
                {
                    continue;
                }

                $count_amounts++;

                $amo = strtr($amo, ',', '.');

                if (preg_match('/^([1-9]\d*)(\.\d{2})?$/', $amo) !== 1)
                {
                    $errors[] = 'Ongeldig bedrag ingevuld.';
                    break;
                }

                $user_id_ary[] = (int) $user_id;
            }

            if (!$count_amounts)
            {
                $errors[] = 'Er is geen enkel bedrag ingevuld.';
            }

            if (!count($errors))
            {
                $db->insert($pp->schema() . '.mollie_payment_requests', [
                    'description'   => $description,
                    'created_by'    => $su->id(),
                ]);

                $request_id = (int) $db->lastInsertId($pp->schema() . '.mollie_payment_requests_id_seq');

                foreach($user_id_ary as $user_id)
                {
                    $user = $users[$user_id];
                    $amo = strtr($amount[$user_id], ',', '.');

                    $db->insert($pp->schema() . '.mollie_payments', [
                        'token'         => $token_generator_service->gen(20),
                        'request_id'    => $request_id,
                        'amount'        => $amo,
                        'user_id'       => $user_id,
                        'currency'      => 'EUR',
                        'created_by'    => $su->id(),
                    ]);

                    $user_cache_service->clear($user_id, $pp->schema());
                }

                $success = [];

                if (count($user_id_ary) === 1)
                {
                    $success[] = 'Betaalverzoek met omschrijving "' . $description . '" aangemaakt.';
                }
                else
                {
                    $success[] = 'Betaalverzoeken met omschrijving "' . $description . '" aangemaakt.';
                }

                $alert_service->success($success);

                return $this->redirectToRoute('mollie_payments', $pp->ary());
            }

            $alert_service->error($errors);
        }

        $out = '<div class="panel panel-warning">';
        $out .= '<div class="panel-heading">';

        $out .= '<form class="form" id="fill_in_aid">';

        $out .= '<div class="form-group">';
        $out .= '<label for="fixed" class="control-label">';
        $out .= 'Bedrag (Invul-hulp)</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-eur"></i>';
        $out .= '</span>';
        $out .= '<input type="number" class="form-control margin-bottom" id="fixed" ';
        $out .= 'min="0" value="" step="0.01">';
        $out .= '</div>';
        $out .= '<p>Hiermee vul je dit bedrag in voor alle accounts hieronder. ';
        $out .= 'Je kan daarna nog individuele bedragen aanpassen ';
        $out .= 'of wissen (op nul zetten) alvorens ';
        $out .= 'betaalverzoeken te creëren. ';
        $out .= 'Enkel gehele getallen zijn mogelijk (geen cijfers na de komma).</p>';
        $out .= '</div>';

        if ($new_users_enabled)
        {
            $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'    => 'omit_new',
                '%label%'   => 'Sla <span class="bg-success text-success">instappers</span> over.',
                '%attr%'    => '',
            ]);
        }

        if ($leaving_users_enabled)
        {
            $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'    => 'omit_leaving',
                '%label%'   => 'Sla <span class="bg-danger text-danger">uitstappers</span> over.',
                '%attr%'    => '',
            ]);
        }

        $out .= '<button class="btn btn-default btn-lg" id="fill-in">';
        $out .= 'Vul in</button>';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get">';
        $out .= '<div class="row">';
        $out .= '<div class="col-xs-12">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="q" name="q" value="';
        $out .= $q;
        $out .= '" placeholder="Filter">';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<ul class="nav nav-tabs">';

        $nav_params = $params;

        foreach ($status_def_ary as $k => $tab)
        {
            $nav_params['status'] = $k;

            $out .= '<li';
            $out .= $params['status'] === $k ? ' class="active"' : '';
            $out .= '>';

            $class_ary = isset($tab['cl']) ? ['class' => 'bg-' . $tab['cl']] : [];

            $out .= $link_render->link('mollie_payments_add', $pp->ary(),
                $nav_params, $tab['lbl'], $class_ary);

            $out .= '</li>';
        }

        $out .= '</ul>';

        $out .= '<form method="post" autocomplete="off">';

        $out .= '<div class="panel panel-info">';

        $out .= '<table class="table table-bordered table-striped ';
        $out .= 'table-hover footable panel-body csv" ';
        $out .= 'data-filtering="true" data-filter-delay="0" ';
        $out .= 'data-filter="#q" data-filter-min="1" data-cascade="true" ';
        $out .= 'data-empty="Er zijn geen gebruikers ';
        $out .= 'volgens de selectiecriteria" ';
        $out .= 'data-sorting="true" ';
        $out .= 'data-filter-placeholder="Zoeken" ';
        $out .= 'data-filter-position="left">';

        $out .= '<thead>';

        $out .= '<tr>';
        $out .= '<th data-sort-initial="true">Account</th>';
        $out .= '<th data-sort-ignore="true">Bedrag</th>';
        $out .= '<th title="Het vorige betaalverzoek">Vorige</th>';
        $out .= '</tr>';

        $out .= '</thead>';
        $out .= '<tbody>';

        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

        foreach($users as $user_id => $user)
        {
            $user_status = $user['status'];

            if (isset($user['adate'])
                && $user['status'] === 1
                && $new_users_enabled
                && $new_user_treshold->getTimestamp() < strtotime($user['adate'] . ' UTC'))
            {
                $user_status = 3;
            }

            if ($user['status'] === 2
                && !$leaving_users_enabled)
            {
                $user_status = 1;
            }

            $out .= '<tr';

            if (isset(StatusCnst::CLASS_ARY[$user_status]))
            {
                $out .= ' class="';
                $out .= StatusCnst::CLASS_ARY[$user_status];
                $out .= '"';
            }

            $out .= '><td>';

            $td = [];

            $td[] = $account_render->link((int) $user_id, $pp->ary());

            $td_inp = '<div class="input-group">';
            $td_inp .= '<span class="input-group-addon">';
            $td_inp .= '<i class="fa fa-eur"></i>';
            $td_inp .= '</span>';
            $td_inp .= '<input type="number" name="amount[' . $user_id . ']" ';
            $td_inp .= 'class="form-control" step="0.01" ';
            $td_inp .= 'value="';
            $td_inp .= $amount[$user_id] ?? '';
            $td_inp .= '" ';
            $td_inp .= 'min="0"';

            if ($user_status === 3)
            {
                $td_inp .= ' data-new-account';
            }

            if ($user_status === 2)
            {
                $td_inp .= ' data-leaving-account';
            }

            $td_inp .= '>';
            $td_inp .= '</div>';

            $td[] = $td_inp;

            if (isset($user['amount']))
            {
                $payment_str = '<span title="';
                $payment_str .= htmlspecialchars($user['description'], ENT_QUOTES);
                $payment_str .= "\n";
                $payment_str .= 'EUR ' . strtr($user['amount'], '.', ',');
                $payment_str .= "\n";
                $payment_str .= ' @';
                $payment_str .= $date_format_service->get($user['last_created_at'], 'day', $pp->schema());
                $payment_str .= '" ';
                $payment_str .= 'class="label label-';

                if ($user['is_canceled'])
                {
                    $payment_str .= 'default">geannuleerd';
                }
                else if ($user['is_paid'])
                {
                    $payment_str .= 'success">betaald';
                }
                else
                {
                    $payment_str .= 'warning">open';
                }

                $td[] = $payment_str . '</span>';
            }
            else
            {
                $td[] = '<i class="fa fa-times" title="Geen betaalverzoeken"></i>';
            }

            $out .= implode('</td><td>', $td);

            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $out .= '<div class="panel-heading">';

        $out .= '<div class="form-group">';
        $out .= '<label for="total" class="control-label">Totaal';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-eur"></i>';
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" id="total" readonly step="0.01">';
        $out .= '</div>';
        $out .= '<p>Exclusief <a href="https://www.mollie.com/nl/pricing/">Mollie transactie-kosten</a>. ';

        $out .= 'Totaal aantal transacties: <strong><span id="transaction_count">0</span></strong>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="description" class="control-label">';
        $out .= 'Omschrijving</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-pencil"></span></span>';
        $out .= '<input type="text" class="form-control" id="description" ';
        $out .= 'name="description" ';
        $out .= 'value="';
        $out .= $description;
        $out .= '" required>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Zorg voor een unieke, bondige en duidelijke omschrijving ';
        $out .= 'zodat ze gemakkelijk terug te vinden is. ';
        $out .= 'Op het rekeningafschrift wordt aan deze omschrijving automatisch ';
        $out .= 'de gebruikers Account Code als prefix toegevoegd. ';
        $out .= 'Let op dat bij ';
        $out .= 'Bancontact de totale lengte van de omschrijving ';
        $out .= 'niet langer kan zijn dan 35 ';
        $out .= 'tekens. Meer tekens worden afgekapt. </p>';
        $out .= '</div>';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'verify',
            '%label%'   => 'Ik heb alles nagekeken.',
            '%attr%'    => ' required',
        ]);

        $out .= $link_render->btn_cancel('mollie_payments', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Betaalverzoeken aanmaken" ';
        $out .= 'name="zend" class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</form>';

        $out .= '<p>Wanneer ingelogd ziet de gebruiker met een openstaand betaalverzoek ';
        $out .= 'bovenaan elke pagina een link om de betaling via de Mollie website ';
        $out .= 'uit te voeren.</p>';

        return $this->render('mollie/mollie_payments_add.html.twig', [
            'content'   => $out,
        ]);
    }
}
