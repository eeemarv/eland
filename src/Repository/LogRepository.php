<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class LogRepository
{
	public function __construct(
		private readonly Db $db,
	)
	{
	}

  public function get_types(
    Schema $schema,
  ):array
  {
    $types = [];
    $stmt = $this->db->prepare('select distinct type
      from xdb.logs
      where schema = :schema
      order by type asc');
    $stmt->bindValue('schema', $schema->str(), Types::STRING);
    $res = $stmt->executeQuery();
    while ($row = $res->fetchAssociative())
    {
      $types[] = $row['type'];
    }
    return $types;
  }
}
