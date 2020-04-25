<?php declare(strict_types=1);

namespace App\Monolog;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ElandProcessor implements EventSubscriberInterface
{
    private $extra = [];

    public function __invoke(array $record):array
    {
        if (isset($this->extra))
        {
            $record['extra'] = array_merge($record['extra'], $this->extra);
        }

        return $record;
    }

    public function onKernelController(ControllerEvent $event):void
    {
        if (!$event->isMasterRequest())
        {
            return;
        }

        $request = $event->getRequest();

        $this->extra = [
            'ip'    => $request->getClientIp(),
        ];

        if (!$request->attributes->has('system'))
        {
            return;
        }

        $this->extra['system'] = $request->attributes->get('system');

        $logins = $request->getSession()->get('logins');

        if ($logins)
        {
            $this->extra['logins'] = $logins;
        }

        $org_system = $request->request->get('org_system');

        if ($org_system)
        {
            $this->extra['org_system'] = $org_system;
        }

        if ($request->attributes->has('role_short'))
        {
            $this->extra['role_short'] = $request->attributes->get('role_short');
        }
    }

    public static function getSubscribedEvents():array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 500],
         ];
    }
}
