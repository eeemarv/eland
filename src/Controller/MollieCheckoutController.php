<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Mollie\Api\MollieApiClient;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MollieCheckoutController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        UserCacheService $user_cache_service,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        MenuService $menu_service,
        LinkRender $link_render,
        HeadingRender $heading_render,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $errors = [];

        $mollie_payment = $db->fetchAssociative('select p.*, r.description
            from ' . $pp->schema() . '.mollie_payments p,
                ' . $pp->schema() . '.mollie_payment_requests r
            where p.request_id = r.id
                and p.id = ?',
            [$id], [\PDO::PARAM_INT]);

        if (!$mollie_payment)
        {
            throw new NotFoundHttpException('Betaling niet gevonden.');
        }

        if (!$su->is_owner($mollie_payment['user_id']))
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot deze pagina.');
        }

        $mollie_apikey = $db->fetchOne('select data->>\'apikey\'
            from ' . $pp->schema() . '.config
            where id = \'mollie\'', [], []);

        if ((!$mollie_payment['is_payed'] || !$mollie_payment['is_canceled']))
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
                if ($request->isMethod('GET'))
                {
                    $alert_service->warning('TEST modus! Er zijn momenteel geen echte betalingen mogelijk.');
                }
            }
        }

        $user = $user_cache_service->get($mollie_payment['user_id'], $pp->schema());

//--------

        $description = $user['code'] . ' ' . $mollie_payment['description'];

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
                        'value'     => $mollie_payment['amount'],
                    ],
                    'locale'        => 'nl_BE',
                    'description' => $description,
                    'redirectUrl' => $link_render->context_url('mollie_checkout', $pp->ary(), ['id' => $id]),
                    'webhookUrl'  => $link_render->context_url('mollie_webhook', ['system' => $pp->system()], []),
                    'metadata'      => [
                        'token' => $mollie_payment['token'],
                    ],
                ]);

                $db->update($pp->schema() . '.mollie_payments', [
                    'mollie_payment_id' => $payment->id,
                ], ['id' => $id]);

                header('Location: ' . $payment->getCheckoutUrl(), true, 303);
                exit;
            }

            $alert_service->error($errors);
        }

        $heading_render->fa('eur');

        $out = '<div class="panel panel-';

        if ($mollie_payment['is_canceled'])
        {
            $heading_render->add('Deze betaling is geannuleerd');
            $out .= 'default';
        }
        else if ($mollie_payment['is_payed'])
        {
            $heading_render->add('Betaling geslaagd!');
            $out .= 'success';
        }
        else
        {
            $heading_render->add('Euro-betaling uitvoeren');
            $out .= 'info';
        }

        $out .= '">';
        $out .= '<div class="panel-heading">';

        if (!($mollie_payment['is_payed'] || $mollie_payment['is_canceled']))
        {
            $out .= '<form method="post">';

            $out .= '<p>Je kreeg van <strong>' . $config_service->get('systemname', $pp->schema());
            $out .= '</strong> het volgende verzoek tot betaling:</p>';
        }

        $out .= '<dl>';
        $out .= '<dt>';
        $out .= 'Bedrag';
        $out .= '</dt>';
        $out .= '<dd>';
        $out .= strtr($mollie_payment['amount'], '.', ',') . ' EUR';
        $out .= '</dd>';
        $out .= '<dt>';
        $out .= 'Omschrijving';
        $out .= '</dt>';
        $out .= '<dd>';
        $out .= $description;
        $out .= '</dd>';
        $out .= '</dd>';

        if (!($mollie_payment['is_payed'] || $mollie_payment['is_canceled']))
        {
            $out .= '<br>';

            $out .= '<input type="submit" name="pay" ';
            $out .= 'value="Online betalen" class="btn btn-lg btn-primary">';
            $out .= '<p>Je wordt geleid naar het beveiligde Mollie ';
            $out .= 'platform voor online betalen.</p>';

            $out .= $form_token_service->get_hidden_input();
            $out .= '</form>';
        }

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('mollie_payments');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
