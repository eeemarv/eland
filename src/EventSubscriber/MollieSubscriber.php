<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Cache\ConfigCache;
use App\Cache\UserInvalidateCache;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\DBAL\Connection as Db;

class MollieSubscriber implements EventSubscriberInterface
{
    const MSG = <<<'TPL'
    Je hebt een openstaand verzoek tot betaling:
    TPL;

    const MSG_MULTI = <<<'TPL'
    Je hebt openstaande verzoeken tot betaling:
    TPL;

    const PAYMENT_REQUEST = <<<'TPL'
    <a href="%link%"><dl><dt>Omschrijving<dt><dd>%description%</dd>
    <dt>Bedrag</dt><dd>%amount% EUR</dd></dl></a>
    TPL;

    public function __construct(
        protected Db $db,
        protected AlertService $alert_service,
        protected PageParamsService $pp,
        protected SessionUserService $su,
        protected UserInvalidateCache $user_invalidate_cache,
        protected LinkRender $link_render,
        protected ConfigCache $config_cache
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

        if (!($this->config_cache->get_bool('mollie.enabled', $this->pp->schema())))
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
            $this->user_invalidate_cache->user($this->su->id(), $this->pp->schema());
            return;
        }

        $info = [];
        $info[] = count($payments) > 1 ? self::MSG_MULTI : self::MSG;

        foreach ($payments as $payment)
        {
            $description = $this->su->code() . ' ' . $payment['description'];
            $info[] = strtr(self::PAYMENT_REQUEST,[
                '%link%'            => $this->link_render->context_path('mollie_checkout',
                    ['system' => $this->pp->system()],
                    ['token' => $payment['token']]),
                '%description%'     => htmlspecialchars($description, ENT_QUOTES),
                '%amount%'          => strtr($payment['amount'], '.', ','),
            ]);
        }

        $this->alert_service->info($info, false);
    }

    public static function getSubscribedEvents():array
    {
        return [
           KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
