<?php declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class CorsSubscriber implements EventSubscriberInterface
{
    protected $env_s3_url;

    public function __construct(
        string $env_s3_url
    )
    {
        $this->env_s3_url = $env_s3_url;
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();

        $allow_origin = rtrim($this->env_s3_url, '/');
        $response->headers->set('Access-Control-Allow-Origin', $allow_origin);
    }

    public static function getSubscribedEvents()
    {
        return [
           'kernel.response' => 'onKernelResponse',
        ];
    }
}