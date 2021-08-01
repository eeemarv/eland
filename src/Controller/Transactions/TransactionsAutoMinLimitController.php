<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TransactionsAutoMinLimitController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/auto-min-limit',
        name: 'transactions_autominlimit',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'transactions',
            'sub_module'    => 'autominlimit',
        ],
    )]

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        LinkRender $link_render,
        PageParamsService $pp,
        ConfigService $config_service,
        FormTokenService $form_token_service
    ):Response
    {
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        if (!$config_service->get_bool('accounts.limits.auto_min.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Submodule auto min limit not enabled.');
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);

                return $this->redirectToRoute('transactions_autominlimit', $pp->ary());
            }

            $percentage = (int) $request->request->get('percentage');
            $exclude_to = $request->request->get('exclude_to', '');
            $exclude_from = $request->request->get('exclude_from', '');

            $config_service->set_int('accounts.limits.auto_min.percentage', $percentage, $pp->schema());
            $config_service->set_str('accounts.limits.auto_min.exclude.to', $exclude_to, $pp->schema());
            $config_service->set_str('accounts.limits.auto_min.exclude.from', $exclude_from, $pp->schema());

            $alert_service->success('De automatische minimum limiet instellingen zijn aangepast.');

            return $this->redirectToRoute('transactions_autominlimit', $pp->ary());
        }
        else
        {
            $percentage = $config_service->get_int('accounts.limits.auto_min.percentage', $pp->schema());
            $exclude_to = $config_service->get_str('accounts.limits.auto_min.exclude.to', $pp->schema());
            $exclude_from = $config_service->get_str('accounts.limits.auto_min.exclude.from', $pp->schema());
        }

        $out = '<div class="panel panel-info">';

        $out .= '<div class="panel-heading"><p>';
        $out .= 'Met dit formulier kan een Automatische Minimum Limiet ingesteld worden. ';
        $out .= 'De individuele Minimum Limiet van Accounts zal zo automatisch lager ';
        $out .= 'worden door ontvangen transacties ';
        $out .= 'tot de ';
        $out .= $link_render->link_no_attr('transactions_system_limits',
            $pp->ary(), [], 'Minimum Systeemslimiet');
        $out .= ' bereikt wordt. ';
        $out .= 'De individuele Account Minimum Limiet wordt gewist wanneer de ';
        $out .= $link_render->link_no_attr('transactions_system_limits',
            $pp->ary(), [], 'Minimum Systeemslimiet');
        $out .= ' bereikt of onderschreden wordt.</p>';
        $out .= '<p>Wanneer geen Minimum Systeemslimiet is ingesteld, ';
        $out .= 'dan blijft de individuele Account Minimum Limiet bij elke ';
        $out .= 'transactie naar het Account telkens dalen.</p>';

        $out .= '<p>Individuele Account Minimum Limieten die ';
        $out .= 'lager zijn dan de algemene Minimum Systeemslimiet ';
        $out .= 'blijven altijd ongewijzigd.</p>';

        $out .= '<form method="post">';

        $out .= '<h3>Voor accounts</h3>';
        $out .= '<p>De automatische minimum limiet is enkel van toepassing op actieve accounts die ';
        $out .= 'rol van gewone gebruiker hebben (user) en die ';
        $out .= 'niet de status uitstapper hebben. Hieronder kunnnen nog verder individuele accounts uitgesloten ';
        $out .= 'worden.</p>';

        $out .= '<div class="form-group">';
        $out .= '<label for="exclusive" class="control-label">';
        $out .= 'Exclusief</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" id="exclusive" name="exclude_to" ';
        $out .= 'value="';
        $out .= $exclude_to;
        $out .= '" ';
        $out .= 'class="form-control">';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Account Codes gescheiden door comma\'s</p>';
        $out .= '</div>';

        $out .= '<hr>';

        $out .= '<h3>Trigger voor daling van de minimum limiet.</h3>';
        $out .= '<h4>Ontvangen transacties laten de minimum limiet dalen.</h4>';

        $out .= '<div class="form-group">';
        $out .= '<label for="trans_percentage" class="control-label">';
        $out .= 'Percentage van ontvangen bedrag</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-percent"></span></span>';
        $out .= '<input type="number" id="percentage" name="percentage" ';
        $out .= 'value="';
        $out .= $percentage;
        $out .= '" ';
        $out .= 'class="form-control">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="trans_exclusive" class="control-label">';
        $out .= 'Exclusief tegenpartijen</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" id="trans_exclusive" name="exclude_from" ';
        $out .= 'value="';
        $out .= $exclude_from;
        $out .= '" ';
        $out .= 'class="form-control">';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Account Codes gescheiden door comma\'s</p>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('transactions_autominlimit', $pp->ary(), []);

        $out .= '&nbsp;';

        $out .= '<input type="submit" value="Aanpassen" name="zend" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('transactions/transactions_autominlimit.html.twig', [
            'content'   => $out,
        ]);
    }
}
