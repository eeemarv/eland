<?php declare(strict_types=1);

namespace App\Controller;

use App\Cnst\BulkCnst;
use App\Cnst\StatusCnst;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\AutoMinLimitService;
use App\Service\ConfigService;
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
use Predis\Client as Predis;
use Psr\Log\LoggerInterface;

class MolliePaymentsAddController extends AbstractController
{
    public function __invoke(
        Predis $predis,
        Request $request,
        string $status,
        Db $db,
        LoggerInterface $logger,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        MenuService $menu_service,
        LinkRender $link_render,
        AccountRender $account_render,
        HeadingRender $heading_render,
        MailQueue $mail_queue,
        TypeaheadService $typeahead_service,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        AutoMinLimitService $autominlimit_service,
        TransactionService $transaction_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
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
        $mail_subject = $request->request->get('mail_subject', '');
        $mail_content = $request->request->get('mail_content', '');
        $mail_cc = $request->request->has('mail_cc');
        $verify = $request->request->has('verify');

        $status_def_ary = UsersListController::get_status_def_ary($config_service, $pp);

        if (isset($status_def_ary[$status]['sql_bind']))
        {
            $params_sql[] = $status_def_ary[$status]['sql_bind'];
        }

        $where_sql[] = $status_def_ary[$status]['sql'];

        $params['status'] = $status;

        $where_sql = count($where_sql) ? implode(' and ', $where_sql) : '1 = 1';

        $users = [];

        $users = $db->fetchAll(
            'select u.id, u.name, u.letscode,
                u.accountrole, u.status, u.adate
            from ' . $pp->schema() . '.users u
                left join ' . $pp->schema() . '.contact c
                    on c.id_user = c.id
            where ' . $where_sql . '
            order by letscode asc', $params_sql);

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!$request->request->has('verify'))
            {
                $errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
            }

            if (!$description)
            {
                $errors[] = 'Vul een omschrijving in.';
            }

            $count = 0;

            foreach ($amount as $uid => $amo)
            {
                if (!isset($selected_users[$uid]))
                {
                    continue;
                }

                if (!$amo)
                {
                    continue;
                }

                $count++;

                if (!filter_var($amo, FILTER_VALIDATE_INT, $filter_options))
                {
                    $errors[] = 'Ongeldig bedrag ingevuld.';
                    break;
                }
            }

            if (!$count)
            {
                $errors[] = 'Er is geen enkel bedrag ingevuld.';
            }

            if (!count($errors))
            {

                // process

                $alert_service->success('E-mails verzonden.');

                $link_render->redirect('mollie_payments', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        if ($request->isMethod('GET'))
        {
            $mail_cc = true;
        }

        $assets_service->add([
            'codemirror',
            'summernote',
            'summernote_email.js',
            'mollie_payments_add.js',
        ]);

        $heading_render->add('Mollie Betaalverzoek aanmaken');
        $heading_render->fa('eur');

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
        $out .= 'min="0">';
        $out .= '</div>';
        $out .= '<p>Hiermee vul je dit bedrag in voor alle accounts hieronder. ';
        $out .= 'Je kan daarna nog individuele bedragen aanpassen ';
        $out .= 'of wissen (op nul zetten) alvorens het ';
        $out .= 'betaalverzoek te creëren.</p>';
        $out .= '</div>';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'omit_new',
            '%label%'   => 'Sla <span class="bg-success text-success">instappers</span> over.',
        ]);

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'omit_leaving',
            '%label%'   => 'Sla <span class="bg-danger text-danger">uitstappers</span> over.',
        ]);

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
        $out .= '<th>E-mail</th>';
        $out .= '<th>Vorige</th>';
        $out .= '</tr>';

        $out .= '</thead>';
        $out .= '<tbody>';

        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

        foreach($users as $user)
        {
            $user_id = $user['id'];
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

            $out .= '"><td>';

            $td = [];

            $td[] = $account_render->link($user_id, $pp->ary());

            $td_inp = '<div class="input-group">';
            $td_inp .= '<span class="input-group-addon">';
            $td_inp .= '<i class="fa fa-eur"></i>';
            $td_inp .= '</span>';
            $td_inp .= '<input type="number" name="amount[' . $user_id . ']" ';
            $td_inp .= 'class="form-control" ';
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

            if (!isset($user['email']) || !$user['email'])
            {
                $td[] = '<i class="fa fa-ok" title="E-mail adres ingesteld"></i>';
            }
            else
            {
                $td[] = '<i class="fa fa-times" title="Geen E-mail adres ingesteld"></i>';
            }

            $td[] = 'vorige';

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
        $out .= '<input type="number" class="form-control" id="total" readonly>';
        $out .= '</div>';
        $out .= '<p>Exclusief <a href="https://www.mollie.com/nl/pricing/">Mollie transactie-kosten</a>.';
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

        $out .= '<div class="pan-sub bg-warning">';
        $out .= '<h3>Betaalverzoek E-mail</h3>';
        $out .= '<p>Deze E-mail wordt verstuurd naar alle accounts ';
        $out .= 'waar een E-mail adres is ingesteld. Zorg ervoor dat ';
        $out .= 'de variable <code>{{ betaal_link }}</code> is opgenomen ';
        $out .= 'in de E-mail (Gebruik de <code>Variabelen</code> knop).';

        $out .= '<div class="form-group">';
        $out .= '<input type="text" class="form-control" id="bulk_mail_subject" name="bulk_mail_subject" ';
        $out .= 'placeholder="Onderwerp" ';
        $out .= 'value="';
        $out .= $mail_subject;
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
        $out .= $mail_content;
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'mail_cc',
            '%label%'   => 'Stuur een kopie met verzendinfo naar mijzelf',
            '%attr%'    => $mail_cc ? ' checked' : '',
        ]);

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'verify',
            '%label%'   => 'Ik heb alles nagekeken.',
            '%attr%'    => ' required',
        ]);

        $out .= $link_render->btn_cancel('mollie_payments', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Betaalverzoek aanmaken" ';
        $out .= 'name="zend" class="btn btn-success btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</form>';

        $out .= '<p>Een betaalverzoek wordt op twee wijzen kenbaar gemaakt ';
        $out .= 'aan de gebruikers: </p>';
        $out .= '<ul><li>Met een betaalverzoek E-mail</li>';
        $out .= '<li>Met een betaalverzoek boodschap bovenaan elke pagina wanneer de gebruiker ';
        $out .= 'ingelogd is. De boodschap verdwijnt van zodra de betaling uitgevoerd is of ';
        $out .= 'geannuleerd door een admin.</li></ul>';

        $menu_service->set('mollie_payments');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
