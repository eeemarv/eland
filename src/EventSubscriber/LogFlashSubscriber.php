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

    if (!$request->attributes->has('system'))
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

    $error_ary = $flashBag->peek('error', []);

    if (count($error_ary))
    {
      $this->add_log('error', $error_ary, $uri, $schema);
    }

    $warning_ary = $flashBag->peek('warning', []);

    if (count($warning_ary))
    {
      $this->add_log('warning', $warning_ary, $uri, $schema);
    }

    $success_ary = $flashBag->peek('success', []);

    if (count($success_ary))
    {
      $this->add_log('success', $success_ary, $uri, $schema);
    }

    $info_ary = $flashBag->peek('info', []);

    if (count($info_ary))
    {
      $this->add_log('info', $info_ary, $uri, $schema);
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

    foreach ($msg_ary as $msg)
    {
      if (is_string($msg))
      {
        $text_ary[] = $msg;
        continue;
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

    $this->logger->debug($log_msg, $log_ary);
  }

  public static function getSubscribedEvents():array
  {
    return [
      KernelEvents::CONTROLLER => 'onKernelController',
    ];
  }
}
