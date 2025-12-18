<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class LogoutRepository
{
	public function __construct(
		private readonly Db $db,
  )
	{
	}

	public function insert(
		int $user_id,
    string $agent,
    string $ip,
		Schema $schema
	):void
	{
		$this->db->insert($schema->str() . '.logout', [
      'user_id' => $user_id,
			'agent'   => $agent,
			'ip'      => $ip,
		], [
      'user_id' => Types::INTEGER,
      'agent'   => Types::STRING,
      'ip'      => Types::STRING,
    ]);
	}
}
