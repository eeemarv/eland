<?php declare(strict_types=1);

namespace App\Controller;

use App\Cnst\BulkCnst;
use App\Cnst\StatusCnst;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TokenGeneratorService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class MolliePaymentsAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        string $status,
        Db $db,
        AlertService $alert_service,
        UserCacheService $user_cache_service,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        MenuService $menu_service,
        LinkRender $link_render,
        AccountRender $account_render,
        HeadingRender $heading_render,
        DateFormatService $date_format_service,
        PageParamsService $pp,
        SessionUserService $su,
        TokenGeneratorService $token_generator_service,
        AssetsService $assets_service
    ):Response
    {
        $errors = [];
        $params = [];
        $where_sql = [];
        $params_sql = [];

        $q = $request->get('q', '');
        $amount = $request->request->get('amount', []);
        $description = trim($request->request->get('description', ''));
        $verify = $request->request->get('verify');

//---------

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

//--------

        $status_def_ary = UsersListController::get_status_def_ary($config_service, $pp);

        if (isset($status_def_ary[$status]['sql_bind']))
        {
            $params_sql[] = $status_def_ary[$status]['sql_bind'];
        }

        $where_sql[] = $status_def_ary[$status]['sql'];

        $params['status'] = $status;

        $where_sql = count($where_sql) ? implode(' and ', $where_sql) : '1 = 1';

        $users = [];

        $stmt = $db->executeQuery(
            'select u.id, u.name, u.fullname, u.letscode,
                u.accountrole, u.status, u.adate,
                p1.is_payed, p1.is_canceled, p1.created_at as last_created_at,
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
            where ' . $where_sql . '
            order by u.letscode asc', $params_sql);

        while($row = $stmt->fetch())
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
                if (!isset($users[$user_id]['letscode'])
                    || $users[$user_id]['letscode'] === '')
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

                    $db->update($pp->schema() . '.users', [
                        'has_open_mollie_payment'   => 't',
                    ], ['id' => $user_id]);

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
                $link_render->redirect('mollie_payments', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $assets_service->add([
            'mollie_payments_add.js',
        ]);

        $heading_render->add('Mollie Betaalverzoeken aanmaken');
        $heading_render->fa('eur');

        $out = '<div class="card bg-warning">';
        $out .= '<div class="card-body">';

        $out .= '<form class="form" id="fill_in_aid">';

        $out .= '<div class="form-group">';
        $out .= '<label for="fixed" class="control-label">';
        $out .= 'Bedrag (Invul-hulp)</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-eur"></i>';
        $out .= '</span>';
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

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'omit_new',
            '%label%'   => 'Sla <span class="bg-success text-success">instappers</span> over.',
            '%attr%'    => '',
        ]);

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'omit_leaving',
            '%label%'   => 'Sla <span class="bg-danger text-danger">uitstappers</span> over.',
            '%attr%'    => '',
        ]);

        $out .= '<button class="btn btn-default btn-lg" id="fill-in">';
        $out .= 'Vul in</button>';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="card bg-info">';
        $out .= '<div class="card-body">';

        $out .= '<form method="get">';
        $out .= '<div class="row">';
        $out .= '<div class="col-xs-12">';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
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

        $out .= '<div class="card bg-info">';

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
                && $new_user_treshold < strtotime($user['adate']))
            {
                $user_status = 3;
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
                else if ($user['is_payed'])
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

        $out .= '<div class="card-body">';

        $out .= '<div class="form-group">';
        $out .= '<label for="total" class="control-label">Totaal';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-eur"></i>';
        $out .= '</span>';
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
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-pencil"></span>';
        $out .= '</span>';
        $out .= '</span>';
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

        $menu_service->set('mollie_payments');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
