<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Service\PageParamsService;

class AlertService
{
	protected $request;
	protected $logger;
	protected $session;
	protected $flashbag;
	protected $pp;

	public function __construct(
		RequestStack $request_stack,
		LoggerInterface $logger,
		SessionInterface $session,
		PageParamsService $pp
	)
	{
		$this->request = $request_stack->getCurrentRequest();
		$this->logger = $logger;
		$this->session = $session;
		$this->pp = $pp;
		$this->flashbag = $this->session->getFlashBag();
	}

	protected function add(string $type, $message):void
	{
		$uri = $this->request->getRequestUri();

		$log_ary = [
			'schema'		=> $this->pp->schema(),
			'alert_type'	=> $type,
		];

		if (is_array($message))
		{
			$log = implode(' -- & ', $message);
			$message = implode('<br>', $message);
			$this->logger->debug('[alert ' . $type . ' ' . $uri . '] ' . $log,
				$log_ary);
		}
		else
		{
			$this->logger->debug('[alert ' . $type . ' ' . $uri . '] ' . $message, $log_ary);
		}

		$this->flashbag->add('alert', [
			'type' 		=> $type,
			'message'	=> $message,
		]);
	}

	public function error($message):void
	{
		$this->add('error', $message);
	}

	function success($message):void
	{
		$this->add('success', $message);
	}

	public function info($message):void
	{
		$this->add('info', $message);
	}

	public function warning($message):void
	{
		$this->add('warning', $message);
	}
}
