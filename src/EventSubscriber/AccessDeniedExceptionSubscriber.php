<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedExceptionSubscriber implements EventSubscriberInterface
{
  public function __construct(
    private readonly UrlGeneratorInterface $url_generator,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function onExceptionEvent(ExceptionEvent $event)
  {
    $exception = $event->getThrowable();

    if ($exception instanceof AccessDeniedException)
    {
      $redirect_url = null;

      if ($this->pp->schema())
      {
        $request = $event->getRequest();

        if ($this->pp->org_schema())
        {
          $redirect_url = $this->url_generator->generate(
            'login',
            [
              'system'    => $this->pp->org_system(),
              'location'  => $request->getRequestUri(),
            ],
          );
        }
        else
        {
          $redirect_url = $this->url_generator->generate(
            'login',
            [
              'system'    => $this->pp->system(),
              'location'  => $request->getRequestUri(),
            ]
          );
        }
      }
      else
      {
        $redirect_url = $this->url_generator->generate('index');
      }
      $response = new RedirectResponse($redirect_url);
      $response->send();
    }
  }

  public static function getSubscribedEvents():array
  {
    return [
      ExceptionEvent::class => ['onExceptionEvent', 1000],
    ];
  }
}
