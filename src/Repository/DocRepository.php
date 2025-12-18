<?php declare(strict_types=1);

namespace App\Repository;

use App\DTO\Schema;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection as Db;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocRepository
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
		$doc = $this->db->fetchAssociative('select *
			from ' . $schema->str() . '.docs
			where id = :id', [
        'id'  => $id,
      ], [
        'id'  => Types::INTEGER,
      ]);

		if (!$doc)
		{
			throw new NotFoundHttpException('Document ' . $id . ' not found.');
		}

		return $doc;
	}

	public function get_map(
    int $map_id,
    Schema $schema,
  ):array
	{
		$map =  $this->db->fetchAssociative('select *
			from ' . $schema->str() . '.doc_maps
			where id = :map_id', [
        'map_id'  => $map_id,
      ], [
        'map_id'  => Types::INTEGER,
      ]);

		if ($map === false)
		{
			throw new NotFoundHttpException('Document map ' . $map_id . ' not found.');
		}

		return $map;
	}

	public function get_map_with_prev_next(
    int $map_id,
    array $visible_ary,
    Schema $schema,
  ):array
	{
    $map = $this->db->fetchAssociative('select s.*
			from (select dm.*, count(d.*) as doc_count,
				lag(dm.id) over (order by dm.name asc) as prev_id,
				lead(dm.id) over (order by dm.name asc) as next_id
				from ' . $schema->str() . '.doc_maps dm
				inner join ' . $schema->str() . '.docs d
					on d.map_id = dm.id
				where d.access in (:visible_ary)
				group by dm.name, dm.id) s
			where s.id = :map_id', [
        'visible_ary' => $visible_ary,
        'map_id'  => $map_id,
      ], [
        'visible_ary' => ArrayParameterType::STRING,
        'map_id'  => Types::INTEGER,
      ]);

		if ($map === false)
		{
			throw new NotFoundHttpException('Document map ' . $map_id . ' not found.');
		}

    return $map;
	}

	public function is_unique_map_name_except_id(
		string $name,
    int $map_id,
    Schema $schema,
	):bool
	{
		$lowercase_name = trim(strtolower($name));

		return $this->db->fetchOne('select id
			from ' . $schema->str() . '.doc_maps
			where id <> :map_id and lower(name) = :lowercase_name', [
        'map_id'  => $map_id,
        'lowercase_name'  => $lowercase_name,
      ], [
        'map_id'  => Types::INTEGER,
        'lowercase_name' => Types::STRING,
      ]) ? false : true;
	}

	public function update_map_name(
		string $name,
		int $map_id,
		Schema $schema
	):bool
	{
		return $this->db->update($schema->str() . '.doc_maps', [
			'name' => $name,
		], [
			'id' => $map_id,
    ], [
      'name'  => Types::STRING,
      'id'    => Types::INTEGER,
    ]) ? true : false;
	}

	public function get_count_for_map_id(
		int $map_id,
		Schema $schema
	):int
	{
		return $this->db->fetchOne('select count(*)
			from ' . $schema->str() . '.docs
			where map_id = :map_id', [
        'map_id'  => $map_id,
      ], [
        'map_id'  => Types::INTEGER,
      ]);
	}

	public function del(
    int $id,
    Schema $schema,
  ):bool
	{
		return $this->db->delete($schema->str() . '.docs', [
      'id' => $id,
    ], [
      'id'  => Types::INTEGER,
    ]) ? true : false;
	}

	public function del_map(
    int $map_id,
    Schema $schema,
  ):bool
	{
		return $this->db->delete($schema->str() . '.doc_maps', [
      'id' => $map_id,
    ], [
      'id'  => Types::INTEGER,
    ]) ? true : false;
	}

	public function get_maps(
    array $visible_ary,
    Schema $schema,
  ):array
	{
    $res = $this->db->executeQuery('select dm.name, dm.id, count(d.*) as doc_count
      from ' . $schema->str() . '.doc_maps dm,
        ' . $schema->str() . '.docs d
			where d.access in (:visible_ary)
				and d.map_id = dm.id
			group by dm.name, dm.id
			order by dm.name asc', [
        'visible_ary' => $visible_ary,
      ], [
        'visible_ary' => ArrayParameterType::STRING,
      ]);

    return $res->fetchAllAssociative();
	}

	public function get_unmapped_docs(
    array $visible_ary,
    Schema $schema,
  ):array
	{
		$res = $this->db->executeQuery('select coalesce(name, original_filename) as name,
      id, filename, access, created_at
        from ' . $schema->str() . '.docs d
			where access in (:visible_ary)
				and map_id is null
			order by name asc', [
        'visible_ary' => $visible_ary,
      ], [
        'visible_ary' => ArrayParameterType::STRING,
      ]);

    return $res->fetchAllAssociative() ?: [];
	}

	public function get_docs_for_map_id(
		int $map_id,
		array $visible_ary,
		Schema $schema,
	):array
	{
		$docs = [];

		$res = $this->db->executeQuery('select
				coalesce(name, original_filename) as name,
				id, filename, access, created_at
			from ' . $schema->str() . '.docs
			where access in (:visble_ary)
				and map_id = :map_id
			order by name, original_filename asc', [
        'visible_ary' => $visible_ary,
        'map_id' => $map_id,
      ], [
        'visible_ary' => ArrayParameterType::STRING,
        'map_id'  => Types::INTEGER,
      ]);

		while ($row = $res->fetchAssociative())
		{
			$docs[] = $row;
		}

		return $docs;
	}

	public function get_map_id_by_name(
		string $map_name,
		Schema $schema
	):int
	{
		$lowercase_map_name = strtolower($map_name);
		$map_id = $this->db->fetchOne('select id
			from ' . $schema->str() . '.doc_maps
			where lower(name) = :lowercase_map_name', [
        'lowercase_map_name'  => $lowercase_map_name,
      ], [
        'lowercase_map_name'  => Types::STRING,
      ]);

		return $map_id ?: 0;
	}

	public function insert_map(
		string $map_name,
		int $user_id,
		Schema $schema
	):int
	{
		$this->db->insert($schema->str() . '.doc_maps', [
			'name'      => $map_name,
			'user_id'   => $user_id,
		], [
      'name'  => Types::STRING,
      'user_id' => Types::INTEGER,
    ]);

		return (int) $this->db->lastInsertId($schema . '.doc_maps_id_seq');
	}

	public function insert_doc(
    array $doc_ary,
    Schema $schema,
  ):bool
	{
		return $this->db->insert($schema->str() . '.docs',
      $doc_ary) ? true : false;
	}

	public function update_doc(
    array $update_ary,
    int $doc_id,
    Schema $schema,
  ):bool
	{
		return $this->db->update($schema->str() . '.docs',
      $update_ary, [
        'id' => $doc_id,
      ], [
        'id'  => Types::INTEGER,
      ]) ? true : false;
	}
}
