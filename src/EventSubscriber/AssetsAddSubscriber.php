<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AssetsService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AssetsAddSubscriber implements EventSubscriberInterface
{
    protected AssetsService $assets_service;
    protected PageParamsService $pp;
    protected SessionUserService $su;

    public function __construct(
        AssetsService $assets_service,
        PageParamsService $pp,
        SessionUserService $su
    )
    {
        $this->assets_service = $assets_service;
        $this->pp = $pp;
        $this->su = $su;
    }

    public function onKernelController(ControllerEvent $event)
    {
        $request = $event->getRequest();

        if ($request->isXmlHttpRequest())
        {
            return;
        }

        $this->assets_service->add([
            'jquery', 'bootstrap', 'fontawesome',
            'footable', 'base.css', 'base.js',
        ]);

        $this->assets_service->add_print_css(['print.css']);

        if (!$request->isMethod('GET'))
        {
            return;
        }

        if (!$request->attributes->has('system'))
        {
            return;
        }

        if (!$this->pp->system())
        {
            return;
        }

        if (!$this->pp->edit_enabled())
        {
            return;
        }

        if (!$this->su->is_admin())
        {
            return;
        }

        $this->assets_service->add([
            'codemirror',
            'summernote',
            'summernote_cms_edit.js',
        ]);
    }

    public static function getSubscribedEvents():array
    {
        return [
           KernelEvents::CONTROLLER => ['onKernelController', 2000],
        ];
    }
}
