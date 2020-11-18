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
    const MSG_WELCOME = <<<'TPL'
    <strong>Welkom bij %system_name%</strong><br>
    TPL;
    const MSG_CURRENCY = <<<'TPL'
    Waardering bij %system_name% gebeurt met '%currency%'.
    %per_hour_ratio% %currency% stemt overeen met 1 uur.<br>
    TPL;
    const MSG_BACK = <<<'TPL'
    Je kan steeds terug naar je eigen Systeem via het menu <strong>Systeem</strong>
    boven in de navigatiebalk.
    TPL;

    protected AlertService $alert_service;
    protected PageParamsService $pp;
    protected ConfigService $config_service;

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

    public function onKernelController(ControllerEvent $event):void
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

        $system_name = $this->config_service->get_str('system.name', $schema);
        $currency = $this->config_service->get_str('transactions.currency.name', $schema);
        $per_hour_ratio = $this->config_service->get_int('transactions.currency.per_hour_ratio', $schema);
        $timebased_enabled = $this->config_service->get_bool('transactions.currency.timebased_en', $schema);
        $transactions_enabled = $this->config_service->get_bool('transactions.enabled', $schema);

        $msg = strtr(self::MSG_WELCOME, [
            '%system_name%'     => $system_name,
        ]);

        if ($transactions_enabled && $timebased_enabled)
        {
            $msg .= strtr(self::MSG_CURRENCY, [
                '%system_name%'         => $system_name,
                '%currency%'            => $currency,
                '%per_hour_ratio%'      => $per_hour_ratio,
            ]);
        }

        $msg .= self::MSG_BACK;

        $this->alert_service->info($msg);
    }

    public static function getSubscribedEvents():array
    {
        return [
           KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
