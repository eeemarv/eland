<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class AlertService
{
	protected Request $request;
	protected LoggerInterface $logger;
	protected SessionInterface $session;
	protected FlashBagInterface $flash_bag;
	protected PageParamsService $pp;

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
		$this->flash_bag = $this->session->getFlashBag();
	}

	protected function add(string $type, $message, bool $log_en):void
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

			if ($log_en)
			{
				$this->logger->debug('[alert ' . $type . ' ' . $uri . '] ' . $log,
					$log_ary);
			}
		}
		else
		{
			if ($log_en)
			{
				$this->logger->debug('[alert ' . $type . ' ' . $uri . '] ' . $message, $log_ary);
			}
		}

		$this->flash_bag->add('alert', [
			'type' 		=> $type,
			'message'	=> $message,
		]);
	}

	public function error($message, bool $log_en = true):void
	{
		$this->add('error', $message, $log_en);
	}

	function success($message, bool $log_en = true):void
	{
		$this->add('success', $message, $log_en);
	}

	public function info($message, bool $log_en = true):void
	{
		$this->add('info', $message, $log_en);
	}

	public function warning($message, bool $log_en = true):void
	{
		$this->add('warning', $message, $log_en);
	}
}
