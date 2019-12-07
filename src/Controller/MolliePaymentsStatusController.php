<?php declare(strict_types=1);

namespace App\Controller;

use App\Cnst\BulkCnst;
use App\Cnst\StatusCnst;
use App\HtmlProcess\HtmlPurifier;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
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
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Mollie\Api\MollieApiClient;
use Predis\Client as Predis;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MolliePaymentsStatusController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        LoggerInterface $logger,
        AlertService $alert_service,
        UserCacheService $user_cache_service,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        MenuService $menu_service,
        LinkRender $link_render,
        AccountRender $account_render,
        HeadingRender $heading_render,
        DateFormatService $date_format_service,
        MailQueue $mail_queue,
        TypeaheadService $typeahead_service,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        HtmlPurifier $html_purifier
    ):Response
    {
        $errors = [];

        $payment = $db->fetchAssoc('select p.*, r.description
            from ' . $pp->schema() . '.mollie_payments p,
                ' . $pp->schema() . '.mollie_payment_requests r
            where p.request_id = r.id
                and p.id = ?', [$id]);

        if (!$payment)
        {
            throw new NotFoundHttpException('Betaling niet gevonden.');
        }

        if (!$su->is_owner($payment['user_id']) && !$su->is_admin())
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot deze pagina.');
        }

        $mollie_apikey = $db->fetchColumn('select data->>\'apikey\'
            from ' . $pp->schema() . '.config
            where id = \'mollie\'');

        if ((!$payment['is_payed'] || !$payment['is_canceled']))
        {
            if (!$mollie_apikey ||
            !(strpos($mollie_apikey, 'test_') === 0
            || strpos($mollie_apikey, 'live_') === 0))
            {
                throw new AccessDeniedHttpException(
                    'Configuratie-fout (Geen Mollie apikey). Contacteer de administratie.');
            }
            else if (strpos($mollie_apikey, 'live_') !== 0)
            {
                $alert_service->warning('TEST modus! Er zijn momenteel geen echte betalingen mogelijk.');
            }
        }

        $user = $user_cache_service->get($payment['user_id'], $pp->schema());

//--------

        $description = $user['letscode'] . ' ' . $payment['description'];

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $mollie = new MollieApiClient();
                $mollie->setApiKey($mollie_apikey);

                $payment = $mollie->payments->create([
                    'amount' => [
                        'currency'  => 'EUR',
                        'value'     => $payment['amount'],
                    ],
                    'description' => $description,
                    'redirectUrl' => $link_render->context_url('mollie_payments_status', $pp->ary(), ['id' => $id]),
                    'webhookUrl'  => $link_render->context_url('mollie_webhook', ['system' => $pp->system()], []),
                ]);
            }

            $alert_service->success('');
            $link_render->redirect('users_show', $pp->ary(), ['id' => $user_id]);

            $alert_service->error($errors);
        }

        $heading_render->fa('eur');

        if ($payment['is_canceled'])
        {
            $heading_render->add('Deze betaling is geannuleerd');
        }
        else if ($payment['is_payed'])
        {
            $heading_render->add('Betaling geslaagd');
        }
        else
        {
            $heading_render->add('Euro-betaling uitvoeren');
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<p>Je kreeg van <strong>' . $config_service->get('systemname', $pp->schema());
        $out .= '</strong> het volgende verzoek tot betaling:</p>';

        $out .= '<dl>';
        $out .= '<dt>';
        $out .= 'Bedrag';
        $out .= '</dt>';
        $out .= '<dd>';
        $out .= strtr($payment['amount'], '.', ',') . ' EUR';
        $out .= '</dd>';
        $out .= '<dt>';
        $out .= 'Omschrijving';
        $out .= '</dt>';
        $out .= '<dd>';
        $out .= $description;
        $out .= '</dd>';
        $out .= '</dd>';

        $out .= '<br>';

        $out .= '<button type="submit" href="#" class="btn btn-primary btn-lg">Online betalen</a>';
        $out .= '<p>Je wordt geleid naar het beveiligde Mollie ';
        $out .= 'platform voor online betalen.</p>';

        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('mollie_payments');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
