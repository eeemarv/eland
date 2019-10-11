<?php declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class PageParamsService
{
	protected $request;
	protected $session;
	protected $schema;

	public function __construct(
		Request $request,
		Session $session,
		string $schema
	)
	{
		$this->request = $request;
		$this->session = $session;
		$this->schema = $schema;

		$this->init();
	}

	private function init():void
	{

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

	public function edit_enabled():bool
	{

	}

	public function ary():array
	{

	}

	public function get_org_system():string
	{

	}
}
