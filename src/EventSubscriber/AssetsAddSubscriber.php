<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AssetsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AssetsAddSubscriber implements EventSubscriberInterface
{
    protected $assets_service;

    public function __construct(
        AssetsService $assets_service
    )
    {
        $this->assets_service = $assets_service;
    }

    public function onKernelController(ControllerEvent $event)
    {
        $this->assets_service->add([
//            'jquery', 'bootstrap',
            'fontawesome',
            'footable',
//            'base.css',
            'base.js',
        ]);

        $this->assets_service->add_print_css(['print.css']);

    }

    public static function getSubscribedEvents()
    {
        return [
           KernelEvents::CONTROLLER => ['onKernelController', 2000],
        ];
    }
}
