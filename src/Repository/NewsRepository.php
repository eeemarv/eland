<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;

class NewsRepository
{
	public function __construct(
		private readonly Db $db,
	)
	{
	}

	public function get(
		int $id,
		Schema $schema,
	):array|false
	{
    $news = $this->db->fetchAssociative('select *
      from ' . $schema->str() . '.news
      where id = :id', [
      'id'  => $id,
    ], [
      'id'  => Types::INTEGER,
    ]);

		return $news;
	}

	public function get_with_prev_next(
		int $id,
		bool $sort_event_at_asc,
		array $visible_ary,
		Schema $schema,
	):array|false
	{
		$order = $sort_event_at_asc ? 'event_at asc, ' : '';

    $news = $this->db->fetchAssociative('select n.*
			from (select *,
      lag(id) over (order by ' . $order . 'created_at asc) as prev_id,
      lead(id) over (order by ' . $order . 'created_at asc) as next_id
        from ' . $schema->str() . '.news
      where access in (:visible_ary)) n
          where n.id = :id', [
      'visible_ary' => $visible_ary,
      'id'  => $id,
    ], [
      'visible_ary' => ArrayParameterType::STRING,
      'id'  => Types::INTEGER,
    ]);

		return $news;
	}

	public function del(
    int $id,
    Schema $schema,
  ):int
	{
		return (int) $this->db->delete($schema->str() . '.news', [
      'id' => $id,
    ], [
      'id'  => Types::INTEGER,
    ]);
	}

	public function get_all(
		bool $event_at_asc_en,
		array $visible_ary,
		Schema $schema,
	):array
	{
		$order = $event_at_asc_en ? 'event_at asc, ' : '';

		$res = $this->db->executeQuery('select *
			from ' . $schema->str() . '.news
			where access in (:visible_ary)
			order by ' . $order . 'created_at desc', [
        'visible_ary' => $visible_ary
      ], [
        'visible_ary' => ArrayParameterType::STRING,
      ]);

		return $res->fetchAllAssociative();
	}

	public function insert(
    string $subject,
    string $content,
    string $access,
    string|null $location,
    \DateTimeImmutable|null $event_at,
		int $user_id,
		Schema $schema,
	):int
	{
		$this->db->insert($schema->str() . '.news', [
      'user_id'     => $user_id,
			'content'	    => $content,
			'subject'	    => $subject,
			'access'      => $access,
			'location'		=> $location,
			'event_at'		=> $event_at,
    ], [
			'user_id'     => Types::INTEGER,
			'content'	    => Types::STRING,
			'subject'	    => Types::STRING,
			'access'      => Types::STRING,
			'location'		=> Types::STRING,
			'event_at'		=> Types::DATE_IMMUTABLE,
    ]);
		return (int) $this->db->lastInsertId($schema->str() . '.news_id_seq');
	}

	public function update(
    int $id,
    string $subject,
    string $content,
    string $access,
    string|null $location,
    \DateTimeImmutable|null $event_at,
		Schema $schema
	):int
	{
		return (int) $this->db->update($schema->str() . '.news', [
			'content'	    => $content,
			'subject'	    => $subject,
			'access'      => $access,
			'location'		=> $location,
			'event_at'		=> $event_at,
    ],[
      'id' => $id,
    ], [
 			'content'	    => Types::STRING,
			'subject'	    => Types::STRING,
			'access'      => Types::STRING,
			'location'		=> Types::STRING,
			'event_at'		=> Types::DATE_IMMUTABLE,
      'id'          => Types::INTEGER,
    ]);
	}
}
