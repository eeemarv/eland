<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Cache\ConfigCache;
use App\Cache\UserCache;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Mollie\Api\MollieApiClient;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MollieCheckoutController extends AbstractController
{
    #[Route(
        '/{system}/mollie/checkout/{token}',
        name: 'mollie_checkout',
        methods: ['GET', 'POST'],
        priority: 30,
        requirements: [
            'token'         => '%assert.big_token%',
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'mollie',
        ],
    )]

    public function __invoke(
        Request $request,
        string $token,
        Db $db,
        AlertService $alert_service,
        AccountRender $account_render,
        UserCache $user_cache,
        FormTokenService $form_token_service,
        ConfigCache $config_cache,
        LinkRender $link_render,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('mollie.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Mollie submodule (users) not enabled.');
        }

        $errors = [];

        $mollie_payment = $db->fetchAssociative('select p.*, r.description
            from ' . $pp->schema() . '.mollie_payments p,
                ' . $pp->schema() . '.mollie_payment_requests r
            where p.request_id = r.id
                and p.token = ?',
                [$token], [\PDO::PARAM_STR]);

        if (!$mollie_payment)
        {
            throw new NotFoundHttpException('Payment request not found.');
        }

        $mollie_apikey = $config_cache->get_str('mollie.apikey', $pp->schema());

        if (!($mollie_payment['is_paid'] || $mollie_payment['is_canceled']))
        {
            if (!$mollie_apikey ||
            !(str_starts_with($mollie_apikey, 'test_')
            || str_starts_with($mollie_apikey, 'live_')))
            {
                throw new AccessDeniedHttpException(
                    'Configuratie-fout (Geen Mollie apikey). Contacteer de administratie.');
            }
            else if (!str_starts_with($mollie_apikey, 'live_'))
            {
                if ($request->isMethod('GET'))
                {
                    $alert_service->warning('TEST modus! Er zijn momenteel geen echte betalingen mogelijk.', false);
                }
            }
        }

        $user = $user_cache->get($mollie_payment['user_id'], $pp->schema());

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
                    'redirectUrl' => $link_render->context_url('mollie_checkout', $pp->ary(), ['token' => $token]),
                    'webhookUrl'  => $link_render->context_url('mollie_webhook', ['system' => $pp->system()], []),
                    'metadata' => [
                        'token' => $mollie_payment['token'],
                    ],
                ]);

                $db->update($pp->schema() . '.mollie_payments', [
                    'mollie_payment_id' => $payment->id,
                ], ['token' => $token]);

                return $this->redirect($payment->getCheckoutUrl(), 303);
            }

            $alert_service->error($errors);
        }

        $out = '<div class="panel panel-';

        if ($mollie_payment['is_canceled'])
        {
            $out .= 'default';
        }
        else if ($mollie_payment['is_paid'])
        {
            $out .= 'success';
        }
        else
        {
            $out .= 'info';
        }

        $out .= '">';
        $out .= '<div class="panel-heading">';

        if (!($mollie_payment['is_paid'] || $mollie_payment['is_canceled']))
        {
            $out .= '<form method="post">';
            $out .= '<p>Je kreeg het volgende verzoek tot betaling:</p>';
        }

        $out .= '<dl>';
        $out .= '<dt>';
        $out .= 'Van';
        $out .= '</dt>';
        $out .= '<dd>';
        $out .= $account_render->str($mollie_payment['user_id'], $pp->schema());
        $out .= '</dd>';
        $out .= '<dt>';
        $out .= 'Aan';
        $out .= '</dt>';
        $out .= '<dd>';
        $out .= $config_cache->get_str('system.name', $pp->schema());
        $out .= '</dd>';
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

        if (!($mollie_payment['is_paid'] || $mollie_payment['is_canceled']))
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

        return $this->render('mollie/mollie_checkout.html.twig', [
            'content'       => $out,
            'is_paid'       => $mollie_payment['is_paid'],
            'is_canceled'   => $mollie_payment['is_canceled'],
        ]);
    }
}
