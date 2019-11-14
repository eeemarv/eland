<?php declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    const LOCALES = [
        'nl'    => 'nl_NL.UTF-8',
        'en'    => 'en_GB.UTF-8',
    ];

    public function onKernelRequest(GetResponseEvent $event)
    {
        $locale = $event->getRequest()->getLocale();

        if (isset(self::LOCALES[$locale]))
        {
            setlocale(LC_TIME, self::LOCALES[$locale]);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
           KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
