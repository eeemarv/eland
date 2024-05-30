<?php declare(strict_types=1);

namespace App\Monolog;

use Monolog\LogRecord;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[Autoconfigure(tags: ['monolog.processor'])]
class ElandProcessor implements EventSubscriberInterface
{
    private $extra = [];

    public function __invoke(LogRecord $record):LogRecord
    {
        if (isset($this->extra))
        {
            $record->extra = [
                ...$record->extra,
                ...$this->extra,
            ];
        }

        return $record;
    }

    public function onKernelController(ControllerEvent $event):void
    {
        if (!$event->isMainRequest())
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

        if ($request->query->has('os'))
        {
            $this->extra['os'] = $request->query->get('os');
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
