<?php declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    const LOCALES = [
        'nl'    => 'nl_NL.UTF-8',
        'en'    => 'en_GB.UTF-8',
    ];

    protected function set_locale(string $locale):void
    {
        if (isset(self::LOCALES[$locale]))
        {
            setlocale(LC_TIME, self::LOCALES[$locale]);
        }
    }

    public function on_kernel_request(RequestEvent $event):void
    {
        $locale = $event->getRequest()->getLocale();
        $this->set_locale($locale);
    }

    public function on_console_command(ConsoleEvent $event):void
    {
        $this->set_locale('nl');
    }

    public static function getSubscribedEvents():array
    {
        return [
           KernelEvents::REQUEST    => 'on_kernel_request',
           ConsoleEvents::COMMAND   => 'on_console_command',
        ];
    }
}
