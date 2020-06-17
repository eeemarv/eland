<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlertService
{
	protected Request $request;
	protected TranslatorInterface $translator;
	protected LoggerInterface $logger;
	protected SessionInterface $session;
	protected FlashBagInterface $flash_bag;
	protected PageParamsService $pp;

	public function __construct(
		RequestStack $request_stack,
		TranslatorInterface $translator,
		LoggerInterface $logger,
		SessionInterface $session,
		PageParamsService $pp
	)
	{
		$this->request = $request_stack->getCurrentRequest();
		$this->translator = $translator;
		$this->logger = $logger;
		$this->session = $session;
		$this->pp = $pp;
		$this->flash_bag = $this->session->getFlashBag();
	}

	protected function add(string $type, string $trans_key, array $parameters = []):void
	{
		$message = $this->translator->trans($trans_key, $parameters, 'alert');

		$uri = $this->request->getRequestUri();

		$log_ary = [
			'schema'		=> $this->pp->schema(),
			'alert_type'	=> $type,
		];

		$this->logger->debug('[alert ' . $type . ' ' . $uri . '] ' . $message, $log_ary);

		$this->flash_bag->add('alert', [
			'type' 		=> $type,
			'message'	=> $message,
		]);
	}

	protected function add_raw_ary(string $type, array $message_ary):void
	{
		$uri = $this->request->getRequestUri();
		$log = implode(' -- & ', $message_ary);

		$log_ary = [
			'schema'		=> $this->pp->schema(),
			'alert_type'	=> $type,
		];

		$this->logger->debug('[alert ' . $type . ' ' . $uri . '] ' . $log,
				$log_ary);

		$message = implode('<br>', $message_ary);

		$this->flash_bag->add('alert', [
			'type' 		=> $type,
			'message'	=> $message,
		]);
	}

	public function error(string $trans_key, array $parameters = []):void
	{
		$this->add('error', $trans_key, $parameters);
	}

	public function error_raw_ary(array $message_ary):void
	{
		$this->add_raw_ary('error', $message_ary);
	}

	public function success(string $trans_key, array $parameters = []):void
	{
		$this->add('success', $trans_key, $parameters);
	}

	public function success_raw_ary(array $message_ary):void
	{
		$this->add_raw_ary('success', $message_ary);
	}

	public function info(string $trans_key, array $parameters = []):void
	{
		$this->add('info', $trans_key, $parameters);
	}

	public function info_raw_ary(array $message_ary):void
	{
		$this->add_raw_ary('info', $message_ary);
	}

	public function warning(string $trans_key, array $parameters = []):void
	{
		$this->add('warning', $trans_key, $parameters);
	}

	public function warning_raw_ary(array $message_ary):void
	{
		$this->add_raw_ary('warning', $message_ary);
	}
}
