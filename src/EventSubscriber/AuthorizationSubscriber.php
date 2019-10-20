<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Render\LinkRender;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class AuthorizationSubscriber implements EventSubscriberInterface
{
    protected $link_render;
    protected $pp;
    protected $su;

    public function __construct(
        LinkRender $link_render,
        PageParamsService $pp,
        SessionUserService $su
    )
    {
        $this->link_render = $link_render;
        $this->pp = $pp;
        $this->su = $su;
    }

    public function onKernelController(ControllerEvent $event)
    {
        $request = $event->getRequest();




    }

    public static function getSubscribedEvents()
    {
        return [
           'kernel.controller' => ['onKernelController', 1500],
        ];
    }
}
