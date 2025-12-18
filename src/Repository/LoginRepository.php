<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class LoginRepository
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
		$this->db->insert($schema->str() . '.login', [
			'user_id'       => $user_id,
			'agent'         => $agent,
			'ip'            => $ip,
		], [
      'user_id'   => Types::INTEGER,
      'agent'     => Types::STRING,
      'ip'        => Types::STRING,
    ]);
	}

  public function get_last_login_ary(
    Schema $schema,
  ):array
  {
    $ary = [];

    $res = $this->db->executeQuery('select user_id, max(created_at) as last_login
      from ' . $schema->str() . '.login
      group by user_id');

    while ($row = $res->fetchAssociative())
    {
      $ary[$row['user_id']] = $row['last_login'];
    }

    return $ary;
  }
}
