<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ModuleCheckSubscriber implements EventSubscriberInterface
{
    const CHECK_MODULES = [
        'forum'         => 'forum_en',
        'contact_form'  => 'contact_form_en',
        'register'      => 'registration_en',
    ];

    protected PageParamsService $pp;
    protected ConfigService $config_service;

    public function __construct(
        PageParamsService $pp,
        ConfigService $config_service
    )
    {
        $this->pp = $pp;
        $this->config_service = $config_service;
    }

    public function onKernelController(ControllerEvent $event):void
    {
        $request = $event->getRequest();

        if (!$request->attributes->has('system'))
        {
            return;
        }

        if (!$this->pp->schema())
        {
            return;
        }

        if (!$request->attributes->has('module'))
        {
            throw new HttpException(500, 'module parameter missing.');
        }

        $module = $request->attributes->get('module');

        if (!isset(self::CHECK_MODULES[$module]))
        {
            return;
        }

        $schema = $this->pp->schema();
        $enabled = $this->config_service->get(self::CHECK_MODULES[$module], $schema) ? true : false;

        if (!$enabled)
        {
            throw new NotFoundHttpException('Page not found. Module not enabled: ' . $module);
        }
    }

    public static function getSubscribedEvents():array
    {
        return [
           KernelEvents::CONTROLLER => ['onKernelController', 1100],
        ];
    }
}
