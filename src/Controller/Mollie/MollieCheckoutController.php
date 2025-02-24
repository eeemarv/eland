<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Cache\ConfigCache;
use App\Form\Type\Mollie\MollieCheckoutType;
use App\Render\LinkRender;
use App\Repository\MollieRepository;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Mollie\Api\MollieApiClient;
use Symfony\Component\Form\FormFactoryInterface;
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
        AlertService $alert_service,
        ConfigCache $config_cache,
        LinkRender $link_render,
        FormFactoryInterface $form_factory,
        MollieRepository $mollie_repository,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('mollie.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Mollie submodule (users) not enabled.');
        }

        $mollie_payment = $mollie_repository->get_payment_by_token($token, $pp->schema());

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

        $description = $mollie_payment['code'] . ' ' . $mollie_payment['description'];

        $form = $form_factory->create(MollieCheckoutType::class);

        $form->handleRequest($request);

        if (!($mollie_payment['is_paid'] || $mollie_payment['is_canceled'])
            && $form->isSubmitted() && $form->isValid())
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

            $mollie_repository->update_mollie_payment_id($token, $payment->id, $pp->schema());

            return $this->redirect($payment->getCheckoutUrl(), 303);
        }

        return $this->render('mollie/mollie_checkout.html.twig', [
            'form'          => $form->createView(),
            'from_user_id'  => $mollie_payment['user_id'],
            'description'   => $description,
            'amount'        => strtr($mollie_payment['amount'], '.', ',') . ' EUR',
            'is_paid'       => $mollie_payment['is_paid'],
            'is_canceled'   => $mollie_payment['is_canceled'],
        ]);
    }
}
