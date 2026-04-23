<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\PageParamsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogFlashSubscriber implements EventSubscriberInterface
{
  public function __construct(
    private readonly PageParamsService $pp,
    private readonly TranslatorInterface $translator,
    private readonly LoggerInterface $logger
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

    if (!$request->attributes->has('schema'))
    {
      return;
    }

    if ($request->isXmlHttpRequest())
    {
      return;
    }

    $session = $request->getSession();

    if (!$session instanceof FlashBagAwareSessionInterface) {
      return;
    }

		$uri = $request->getRequestUri();

    $schema = $this->pp->schema();

    $flashBag = $session->getFlashBag();

    $error_ary = $flashBag->peek(
      type: 'error',
      default: [],
    );

    if (count($error_ary))
    {
      $this->add_log(
        type: 'error',
        msg_ary: $error_ary,
        uri: $uri,
        schema: $schema
      );
    }

    $warning_ary = $flashBag->peek(
      type: 'warning',
      default: [],
    );

    if (count($warning_ary))
    {
      $this->add_log(
        type: 'warning',
        msg_ary: $warning_ary,
        uri: $uri,
        schema: $schema,
      );
    }

    $success_ary = $flashBag->peek(
      type: 'success',
      default: [],
    );

    if (count($success_ary))
    {
      $this->add_log(
        type: 'success',
        msg_ary: $success_ary,
        uri: $uri,
        schema:$schema,
      );
    }

    $info_ary = $flashBag->peek(
      type: 'info',
      default: [],
    );

    if (count($info_ary))
    {
      $this->add_log(
        type: 'info',
        msg_ary: $info_ary,
        uri: $uri,
        schema: $schema,
      );
    }
  }

  private function add_log(
    string $type,
    array $msg_ary,
    string $uri,
    string $schema
  )
  {
    $text_ary = [];
    $ln = count($msg_ary);
    $count = 0;

    foreach ($msg_ary as $msg)
    {
      $count++;

      if (is_string($msg))
      {
        $text_ary[] = $msg;
        continue;
      }

      if (isset($msg['user']))
      {
        $u_str = $msg['user']['code'] ?? '***';
        $u_str .= ' ';
        $u_str .= $msg['user']['name'];
        if ($count !== $ln)
        {
          $u_str .= ',';
        }
        $u_str .= ' ';
        $text_ary[] = $u_str;
        continue;
      }

      if (!isset($msg['key']))
      {
        throw new \Exception('Missing translation key');
      }

      if (!is_string($msg['key']))
      {
        throw new \Exception('Key should be string');
      }

      $params = $msg['params'] ?? [];
      $text_ary[] = $this->translator->trans($msg['key'], $params);
    }

    $log_msg = '[alert ' . $type . ' ' . $uri . '] ';
    $log_msg .= implode(' -- & -- ', $text_ary);

		$log_ary = [
			'schema'		  => $schema,
			'alert_type'	=> $type,
      'uri'         => $uri,
		];

    $this->logger->debug(
      message: $log_msg,
      context: $log_ary
    );
  }

  public static function getSubscribedEvents():array
  {
    return [
      KernelEvents::CONTROLLER => 'onKernelController',
    ];
  }
}
