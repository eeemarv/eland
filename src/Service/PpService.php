<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class PpService
{
	protected $request;
	protected $logger;
	protected $session;
	protected $flashbag;
	protected $schema;

	public function __construct(
		Request $request,
		LoggerInterface $logger,
		Session $session,
		string $schema
	)
	{
		$this->request = $request;
		$this->logger = $logger;
		$this->session = $session;
		$this->schema = $schema;
		$this->flashbag = $this->session->getFlashBag();
	}

	public function is_admin():bool
	{

	}

	public function is_user():bool
	{

	}

	public function is_guest():bool
	{

	}

	public function ary():array
	{

	}

	public function get_org_system():string
	{

	}
}
