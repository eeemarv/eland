<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RetainPageSubscriber implements EventSubscriberInterface
{
  public function __construct(
    private readonly PageParamsService $pp,
    private readonly SessionUserService $su,
  )
  {
  }

  public function onKernelController(ControllerEvent $event):void
  {
    if (!$event->isMainRequest())
    {
      return;
    }

    $request = $event->getRequest();

    if (!$request->isMethod('GET'))
    {
      return;
    }

    if ($request->isXmlHttpRequest())
    {
      return;
    }

    if (!$request->attributes->has('schema'))
    {
      return;
    }

    if (!$this->pp->schema())
    {
      return;
    }

    if ($this->pp->is_anonymous())
    {
      return;
    }

    if (!$this->su->is_system_self())
    {
      return;
    }

    $session = $request->getSession();

    $session->set(
      name: 'last:' . $this->pp->schema(),
      value: [
        'path'  => $request->getRequestUri(),
        'route' => $this->pp->route(),
        'role_short'  => $this->pp->role_short(),
      ],
    );
  }

  public static function getSubscribedEvents():array
  {
    return [
      KernelEvents::CONTROLLER => 'onKernelController',
    ];
  }
}
