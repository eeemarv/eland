<?php declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected string $env_s3_url
    )
    {
    }

    public function onKernelResponse(ResponseEvent $event):void
    {
        $response = $event->getResponse();

        $allow_origin = rtrim($this->env_s3_url, '/');
        $response->headers->set('Access-Control-Allow-Origin', $allow_origin);
    }

    public static function getSubscribedEvents(): array
    {
        return [
           KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
