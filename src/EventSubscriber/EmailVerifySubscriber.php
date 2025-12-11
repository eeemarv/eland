<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\EmailSentRepository;
use App\Service\SystemsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

class EmailVerifySubscriber implements EventSubscriberInterface
{
  public function __construct(
    private readonly EmailSentRepository $email_sent_repository,
    private readonly SystemsService $systems_service,
  )
  {
  }

  public function onKernelController(ControllerEvent $event):void
  {
    $request = $event->getRequest();

    if (!$request->isMethod('GET'))
    {
      return;
    }

    if (!$request->query->has('et'))
    {
      return;
    }

    if ($request->isXmlHttpRequest())
    {
      return;
    }

    $et = $request->query->get('et');

    if (strlen($et) !== 22)
    {
      return;
    }

    $email_token = Uuid::fromBase58($et);

    $schema = null;

    if ($request->attributes->has('schema'))
    {
      if ($request->attributes->has('role_short')
        && $request->attributes->get('role_short') === 'g'
        && $request->query->has('ets')
      )
      {
        // link refers to other system than email
        $schema = $request->query->get('ets');
        $request->query->remove('ets');
      }
      else
      {
        $schema = $request->attributes->get('schema');
      }
    }
    else if ($request->query->has('schema'))
    {
      $schema = $request->query->get('schema');
      $request->query->remove('ets');
    }

    if (isset($schema)
      && !$this->systems_service->has_schema($schema)
    )
    {
      return;
    }

    $this->email_sent_repository->register_on_email_token(
      email_token: $email_token,
      path_info: $request->getPathInfo(),
      schema: $schema,
    );

    $request->query->remove('et');
  }

  public static function getSubscribedEvents():array
  {
    return [
      KernelEvents::CONTROLLER => 'onKernelController',
    ];
  }
}
