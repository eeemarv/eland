<?php declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class StatusAttributeAccessSubscriber implements EventSubscriberInterface
{
    public function onKernelController(ControllerEvent $event):void
    {
        $request = $event->getRequest();

        $system = $request->attributes->get('system');

        if (!isset($system))
        {
            return;
        }

        $status = $request->attributes->get('status');

        if (!isset($status))
        {
            return;
        }

        $role_short = $request->attributes->get('role_short');

        if (!isset($role_short))
        {
            return;
        }

        if ($role_short === 'a')
        {
            return;
        }

        if (in_array($role_short, ['u', 'g']) && in_array($status, ['active', 'new', 'leaving']))
        {
            return;
        }

        if ($role_short === 'u' && $status === 'intersystem')
        {
            return;
        }

        throw new AccessDeniedHttpException('Access denied for status ' . $status);
    }

    public static function getSubscribedEvents():array
    {
        return [
           KernelEvents::CONTROLLER => ['onKernelController', 1200],
        ];
    }
}
