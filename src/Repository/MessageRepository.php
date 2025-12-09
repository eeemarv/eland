<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MessageRepository
{
	public function __construct(
		private readonly Db $db
	)
	{
	}

	public function get(
    int $id,
    Schema $schema,
  ):array
	{
    $message = $this->db->fetchAssociative('select *
      from ' . $schema->str() . '.messages
      where id = :id', [
        'id' => $id,
      ], [
        'id' => Types::INTEGER,
      ]);

		if (!$message)
		{
			throw new NotFoundHttpException('Message ' . $id . ' not found.');
    }

		return $message;
	}

  // not used yet
	public function get_prev_id(
		int $ref_id,
		array $visible_ary,
		Schema $schema
	):int
	{
    $res = $this->db->executeQuery('select m.id
      from ' . $schema->str() . '.messages m,
      ' . $schema->str() . '.users u
			where m.id > :ref_id
				and u.status in (1, 2)
				and m.access in (:visible_ary)
          order by m.id asc
			limit 1',[
        'ref_id' => $ref_id,
        'visible_ary' => $visible_ary,
      ], [
        'ref_id' => Types::INTEGER,
        'visible_ary' => ArrayParameterType::STRING,
      ]);

		return $res->fetchOne() ?: 0;
	}

  // not used yet
	public function get_next_id(
		int $ref_id,
		array $visible_ary,
		Schema $schema
	):int
	{
    $res = $this->db->executeQuery('select m.id
      from ' . $schema->str() . '.messages m,
          ' . $schema->str() . '.users u
			where m.id < :ref_id
				and u.status in (1, 2)
				and m.access in (:visible_ary)
            order by m.id desc
			limit 1', [
        'ref_id' => $ref_id,
        'visible_ary' => $visible_ary,
      ], [
        'ref_id' => Types::INTEGER,
        'visible_ary' => ArrayParameterType::STRING,
      ]);

		return $res->fetchOne() ?: 0;
	}

  // not used yet
	public function del(
    int $id,
    Schema $schema,
  ):bool
	{
		return $this->db->delete($schema->str() . '.messages', [
      'id' => $id,
    ], [
      'id'  => Types::INTEGER,
    ]) ? true : false;
	}

  // not used yet
	public function insert(
    array $message,
    Schema $schema,
  ):int
	{
		$this->db->insert($schema->str() . '.messages', $message);
		return (int) $this->db->lastInsertId($schema . '.messages_id_seq');
	}

	public function update(
    array $message,
    int $id,
    Schema $schema,
  ):bool
	{
		return $this->db->update($schema->str() . '.messages', $message, [
      'id' => $id,
    ], [
      'id'  => Types::INTEGER,
    ]) ? true : false;
	}

  // not used yet
	public function get_count_for_user_id(
		int $user_id,
		Schema $schema
	):int
	{
    return $this->db->fetchOne('select count(*)
      from ' . $schema->str() . '.messages
      where user_id = :user_id', [
        'user_id' => $user_id,
      ], [
        'user_id' => Types::INTEGER,
      ]);
	}

  // not used yet
	public function del_for_user_id(
		int $user_id,
		Schema $schema
	):void
	{
		$this->db->delete($schema->str() . '.messages', [
      'user_id' => $user_id,
    ], [
      'user_id' => Types::INTEGER,
    ]);
	}

  // not used yet
	public function add_image_file(
		string $image_filename,
		int $id,
		Schema $schema,
	):void
	{
		$this->db->executeStatement('update ' . $schema->str() . '.messages
			set image_files = coalesce(image_files, \'[]\') || :image_filename
			where id = :id', [
        'image_filename' => $image_filename,
        'id'  => $id,
      ], [
        'image_filename' => Types::JSON,
        'id'  => Types::INTEGER,
      ]);
	}

	public function update_image_files(
		array $image_files,
		int $id,
		Schema $schema
	):void
	{
    $image_files = json_encode(array_values($image_files));

		$this->db->update($schema->str() . '.messages', [
      'image_files' => $image_files,
    ], [
      'id' => $id,
    ], [
      'image_files' => Types::JSON,
      'id'  => Types::INTEGER,
    ]);
	}

	public function get_max_id(
    Schema $schema
  ):int
	{
		return $this->db->fetchOne('select max(id)
			from ' . $schema->str() . '.messages') ?: 0;
	}

  public function get_counts_for_each_user(
    Schema $schema,
  ):array
  {
    $msgs_count = [];
    $res = $this->db->executeQuery('select m.user_id,
      count(m.id) filter (where m.offer_want = \'offer\') as offers,
      count(m.id) filter (where m.offer_want = \'want\') as wants,
      count(m.id) as total
      from ' . $schema->str() . '.messages m
      group by m.user_id');

    while ($row = $res->fetchAssociative())
    {
      $msgs_count[$row['user_id']]['offers'] = $row['offers'];
      $msgs_count[$row['user_id']]['wants'] = $row['wants'];
      $msgs_count[$row['user_id']]['total'] = $row['total'];
    }

    return $msgs_count;
  }
}
