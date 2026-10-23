<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class WebPushRepository
{
	public function __construct(
    private readonly Db $db
  )
	{
	}

  public function insert_subscription(
    string $endpoint,
    array $keys,
    int $user_id,
    Schema $schema,
  ):bool
  {
    try
    {
      $stmt = $this->db->prepare('insert into ' . $schema->str() . '.webpush_subscriptions
        (endpoint, keys, user_id)
        values(:endpoint, :keys, :user_id)');
      $stmt->bindValue('endpoint', $endpoint, Types::TEXT);
      $stmt->bindValue('keys', $keys, Types::JSONB);
      $stmt->bindValue('user_id', $user_id, Types::INTEGER);
      $row_count = (int) $stmt->executeStatement();
    }
    catch (\Exception $e)
    {
      $this->db->rollback();
      return false;
    }

    return $row_count === 1;
  }


}
