<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpFoundation\Request;

class LogoutRepository
{
	protected Db $db;

	public function __construct(Db $db)
	{
		$this->db = $db;
	}

	public function insert(
		int $user_id,
		Request $request,
		string $schema
	):void
	{
		$agent = $request->server->get('HTTP_USER_AGENT');
		$ip = $request->getClientIp();

		$this->db->insert($schema . '.logout', [
			'user_id'       => $user_id,
			'agent'         => $agent,
			'ip'            => $ip,
		]);
	}
}
