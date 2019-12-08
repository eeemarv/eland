<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class WelcomeGuestSubscriber implements EventSubscriberInterface
{
    const MSG = <<<'TPL'
    <strong>Welkom bij %system_name%</strong><br>
    Waardering bij %system_name% gebeurt met '%currency%'.
    %currency_ratio% %currency% stemt overeen met 1 uur.<br>
    Je kan steeds terug naar je eigen Systeem via het menu <strong>Systeem</strong>
    boven in de navigatiebalk.
    TPL;

    protected $alert_service;
    protected $pp;
    protected $config_service;

    public function __construct(
        AlertService $alert_service,
        PageParamsService $pp,
        ConfigService $config_service
    )
    {
        $this->alert_service = $alert_service;
        $this->pp = $pp;
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

        if ($request->isXmlHttpRequest())
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
        ]);

        $this->alert_service->info($msg);
    }

    public static function getSubscribedEvents()
    {
        return [
           KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
