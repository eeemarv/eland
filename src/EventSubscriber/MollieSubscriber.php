<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Form\Type\Mollie\MollieCheckoutType;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

class MollieSubscriber implements EventSubscriberInterface
{
    const MSG = <<<'TPL'
    <h3>Je hebt een openstaand verzoek tot betaling</h3>
    TPL;

    const MSG_MULTI = <<<'TPL'
    <h3>Je hebt openstaande verzoeken tot betaling</h3>
    TPL;

    const PAYMENT_REQUEST = <<<'TPL'
    <form action="%link%" method="post">
    <dl>
    <dt><td>Omschrijving</dt><dd>%description%</dd>
    <dt><td>Bedrag</dt><dd>%amount% EUR</dd>
    </dl>
    <br>
    <input type="submit" name="pay" value="Online betalen" class="btn btn-lg btn-primary">
    <p>Je wordt geleid naar het beveiligde Mollie platform voor online betalen.</p>
    %form_token_hidden_input%
    </form>';
    TPL;

    public function __construct(
        protected Db $db,
        protected AlertService $alert_service,
        protected FormTokenService $form_token_service,
        protected PageParamsService $pp,
        protected SessionUserService $su,
        protected UserCacheService $user_cache_service,
        protected LinkRender $link_render,
        protected ConfigService $config_service,
        protected FormFactoryInterface $form_factory,
        protected Environment $twig
    )
    {
    }

    public function onKernelController(ControllerEvent $event):void
    {
        $request = $event->getRequest();

        if (!$request->isMethod('GET'))
        {
            return;
        }

        if ($request->isXmlHttpRequest())
        {
            return;
        }

        if (!$request->attributes->has('system'))
        {
            return;
        }

        if (!$this->pp->system())
        {
            return;
        }

        if (!($this->pp->is_admin() || $this->pp->is_user()))
        {
            return;
        }

        if (!($this->config_service->get_bool('mollie.enabled', $this->pp->schema())))
        {
            return;
        }

        if (!$this->su->has_open_mollie_payment())
        {
            return;
        }

        $route = $request->attributes->get('_route');

        if (str_starts_with($route, 'mollie_'))
        {
            return;
        }

        $payments = $this->db->fetchAllAssociative('select p.amount, p.token, r.description
            from ' . $this->pp->schema() . '.mollie_payments p,
                ' . $this->pp->schema() . '.mollie_payment_requests r
            where p.request_id = r.id
                and user_id = ?
                and is_canceled = \'f\'::bool
                and is_paid = \'f\'::bool',
            [$this->su->id()], [\PDO::PARAM_INT]);

        if (!$payments)
        {
            error_log('User sync no payments in subscriber. Clear cache ++');
            $this->user_cache_service->clear($this->su->id(), $this->pp->schema());
            return;
        }

        $info = [];
        $info[] = count($payments) > 1 ? self::MSG_MULTI : self::MSG;

        $mollie_checkout_ary =[];

        foreach ($payments as $payment)
        {
            $description = $this->su->code() . ' ' . $payment['description'];
            $info[] = strtr(self::PAYMENT_REQUEST,[
                '%link%'            => $this->link_render->context_path('mollie_checkout',
                ['system' => $this->pp->system()],
                ['token' => $payment['token']]),
                '%description%'     => htmlspecialchars($description, ENT_QUOTES),
                '%amount%'          => strtr($payment['amount'], '.', ','),
                '%form_token_hidden_input%' => $this->form_token_service->get_hidden_input(),
            ]);

            $form = $this->form_factory->create(MollieCheckoutType::class, [], [
                'action' => $this->link_render->context_path('mollie_checkout',
                    ['system' => $this->pp->system()],
                    ['token' => $payment['token']]),
            ]);

            $mollie_checkout_ary[] = [
                'form'          => $form->createView(),
                'from_user_id'  => $this->su->id(),
                'description'   => $description,
                'amount'        => strtr($payment['amount'], '.', ',') . ' EUR',
            ];
        }

        $this->twig->addGlobal('mollie_checkout_ary', $mollie_checkout_ary);

        $this->alert_service->info($info, false);
    }

    public static function getSubscribedEvents():array
    {
        return [
           KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
