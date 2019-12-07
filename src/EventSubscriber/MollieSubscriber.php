<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
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

    protected $db;
    protected $alert_service;
    protected $pp;
    protected $su;
    protected $user_cache_service;
    protected $link_render;
    protected $config_service;

    public function __construct(
        Db $db,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        UserCacheService $user_cache_service,
        LinkRender $link_render,
        ConfigService $config_service
    )
    {
        $this->db = $db;
        $this->alert_service = $alert_service;
        $this->pp = $pp;
        $this->su = $su;
        $this->user_cache_service = $user_cache_service;
        $this->link_render = $link_render;
        $this->config_service = $config_service;
    }

    public function onKernelController(ControllerEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->isMethod('GET'))
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

        if (!$this->su->has_open_mollie_payment())
        {
            return;
        }

        $route = $request->attributes->get('_route');

        if ($route === 'mollie_checkout')
        {
            return;
        }

        $payments = $this->db->fetchAll('select p.*, r.description
            from ' . $this->pp->schema() . '.mollie_payments p,
                ' . $this->pp->schema() . '.mollie_payment_requests r
            where p.request_id = r.id
                and user_id = ?
                and is_canceled = \'f\'::bool
                and is_payed = \'f\'::bool', [$this->su->id()]);

        if (!$payments)
        {
            error_log('User sync no payments in subscriber. ++');

            $this->db->update($this->pp->schema() . '.users', [
                'has_open_mollie_payment' => 'f',
            ], ['id' => $this->su->id()]);

            $this->user_cache_service->clear($this->su->id(), $this->pp->schema());
            return;
        }

        $info = [];
        $info[] = count($payments) > 1 ? self::MSG_MULTI : self::MSG;

        foreach ($payments as $payment)
        {
            $description = $this->su->code() . ' ' . $payment['description'];
            $info[] = strtr(self::PAYMENT_REQUEST,[
                '%link%'            => $this->link_render->context_path('mollie_checkout',
                    $this->pp->ary(), ['id' => $payment['id']]),
                '%description%'     => htmlspecialchars($description, ENT_QUOTES),
                '%amount%'          => strtr($payment['amount'], '.', ','),
            ]);
        }

        $this->alert_service->info($info);
    }

    public static function getSubscribedEvents()
    {
        return [
           KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
