<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AssetsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class InitAssetsSubscriber implements EventSubscriberInterface
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
            'jquery', 'bootstrap', 'fontawesome',
            'footable', 'base.css', 'base.js',
        ]);

        $this->assets_service->add_print_css(['print.css']);

    }

    public static function getSubscribedEvents()
    {
        return [
           'kernel.controller' => ['onKernelController', 2000],
        ];
    }
}
