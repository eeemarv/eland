<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class WelcomeGuestSubscriber implements EventSubscriberInterface
{
    const MSG = <<<'TPL'
    <strong>Welkom bij %system_name%</strong>
    <br>
    Waardering bij %system_name% gebeurt met '%currency%'.
    %currency_ratio% %currency% stemt overeen met 1 uur.<br> %context_msg%
    TPL;

    const ELAS_CONTEXT_MSG = <<<'TPL'
    Je bent ingelogd als gast. Je kan informatie
    raadplegen maar niets wijzigen. Transacties moet je
    ingeven in je eigen Systeem.
    TPL;

    const ELAND_CONTEXT_MSG = <<<'TPL'
    Je kan steeds terug naar je eigen Systeem via het menu <strong>Systeem</strong>
    boven in de navigatiebalk.
    TPL;

    protected $alert_service;
    protected $pp;
    protected $su;
    protected $config_service;

    public function __construct(
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        ConfigService $config_service
    )
    {
        $this->alert_service = $alert_service;
        $this->pp = $pp;
        $this->su = $su;
        $this->config_service = $config_service;
    }

    public function onKernelController(ControllerEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->attributes->has('system'))
        {
            return;
        }

        if (!$this->pp->is_guest())
        {
            return;
        }

        if (!$request->query->has('welcome'))
        {
            return;
        }

        $schema = $this->pp->schema();

        if (!$this->config_service->get_intersystem_en($schema))
        {
            return;
        }

        $system_name = $this->config_service->get('systemname', $schema);
        $currency = $this->config_service->get('currency', $schema);
        $currency_ratio = $this->config_service->get('currencyratio', $schema);

        $msg = strtr(self::MSG, [
            '%system_name%'     => $system_name,
            '%currency%'        => $currency,
            '%currency_ratio%'  => $currency_ratio,
            '%context_msg%'     => $this->su->is_elas_guest() ? self::ELAND_CONTEXT_MSG : self::ELAND_CONTEXT_MSG,
        ]);

        $this->alert_service->info($msg);
    }

    public static function getSubscribedEvents()
    {
        return [
           'kernel.controller' => 'onKernelController',
        ];
    }
}