<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocRepository
{
	public function __construct(
		protected Db $db
	)
	{
	}

	public function get(int $id, string $schema):array
	{
		$doc = $this->db->fetchAssociative('select *
			from ' . $schema . '.docs
			where id = ?', [$id]);

		if (!$doc)
		{
			throw new NotFoundHttpException('Document ' . $id . ' not found.');
		}

		return $doc;
	}

	public function get_map(int $map_id, string $schema):array
	{
		$map =  $this->db->fetchAssociative('select *
			from ' . $schema . '.doc_maps
			where id = ?', [$map_id]);

		if ($map === false)
		{
			throw new NotFoundHttpException('Document map ' . $map_id . ' not found.');
		}

		return $map;
	}

	public function get_map_with_prev_next(int $map_id, array $visible_ary, string $schema):array
	{
        $map = $this->db->fetchAssociative('select s.*
			from (select dm.*, count(d.*) as doc_count,
				lag(dm.id) over (order by dm.name asc) as prev_id,
				lead(dm.id) over (order by dm.name asc) as next_id
				from ' . $schema . '.doc_maps dm
				inner join ' . $schema . '.docs d
					on d.map_id = dm.id
				where d.access in (?)
				group by dm.name, dm.id) s
			where s.id = ?',
            [$visible_ary, $map_id],
            [Db::PARAM_STR_ARRAY, \PDO::PARAM_INT]);

		if ($map === false)
		{
			throw new NotFoundHttpException('Document map ' . $map_id . ' not found.');
		}

        return $map;
	}

	public function is_unique_map_name_except_id(
		string $name, int $map_id, string $schema
	):bool
	{
		$lowercase_name = trim(strtolower($name));

		return $this->db->fetchOne('select id
			from ' . $schema . '.doc_maps
			where id <> ? and lower(name) = ?',
			[$map_id, $lowercase_name],
			[\PDO::PARAM_INT, \PDO::PARAM_STR]) ? false : true;
	}

	public function update_map_name(
		string $name,
		int $map_id,
		string $schema
	):bool
	{
		return $this->db->update($schema . '.doc_maps', [
			'name' => $name,
		], [
			'id' => $map_id,
		]) ? true : false;
	}

	public function get_count_for_map_id(
		int $map_id,
		string $schema
	):int
	{
		return $this->db->fetchOne('select count(*)
			from ' . $schema . '.docs
			where map_id = ?',
			[$map_id],
			[\PDO::PARAM_INT]);
	}

	public function del(int $id, string $schema):bool
	{
		return $this->db->delete($schema . '.docs',
			['id' => $id]) ? true : false;
	}

	public function del_map(int $map_id, string $schema):bool
	{
		return $this->db->delete($schema . '.doc_maps',
			['id' => $map_id]) ? true : false;
	}

	public function get_maps(array $visible_ary, string $schema):array
	{
        $res = $this->db->executeQuery('select dm.name, dm.id, count(d.*) as doc_count
            from ' . $schema . '.doc_maps dm, ' . $schema . '.docs d
			where d.access in (?)
				and d.map_id = dm.id
			group by dm.name, dm.id
			order by dm.name asc',
            [$visible_ary],
            [Db::PARAM_STR_ARRAY]);

        return $res->fetchAllAssociative() ?: [];
	}

	public function get_unmapped_docs(array $visible_ary, string $schema):array
	{
		$res = $this->db->executeQuery('select coalesce(name, original_filename) as name,
				id, filename, access, created_at
            from ' . $schema . '.docs d
			where access in (?)
				and map_id is null
			order by name asc',
            [$visible_ary],
            [Db::PARAM_STR_ARRAY]);

        return $res->fetchAllAssociative() ?: [];
	}

	public function get_docs_for_map_id(
		int $map_id,
		array $visible_ary,
		string $schema
	):array
	{
		$docs = [];

		$res = $this->db->executeQuery('select
				coalesce(name, original_filename) as name,
				id, filename, access, created_at
			from ' . $schema . '.docs
			where access in (?)
				and map_id = ?
			order by name, original_filename asc',
			[$visible_ary, $map_id],
			[Db::PARAM_STR_ARRAY, \PDO::PARAM_INT]);

		while ($row = $res->fetchAssociative())
		{
			$docs[] = $row;
		}

		return $docs;
	}

	public function get_map_id_by_name(
		string $map_name,
		string $schema
	):int
	{
		$lowercase_map_name = strtolower($map_name);
		$map_id = $this->db->fetchOne('select id
			from ' . $schema . '.doc_maps
			where lower(name) = ?',
			[$lowercase_map_name],
			[\PDO::PARAM_STR]);

		return $map_id ?: 0;
	}

	public function insert_map(
		string $map_name,
		int $user_id,
		string $schema
	):int
	{
		$this->db->insert($schema . '.doc_maps', [
			'name'      => $map_name,
			'user_id'   => $user_id,
		]);

		return (int) $this->db->lastInsertId($schema . '.doc_maps_id_seq');
	}

	public function insert_doc(array $doc_ary, string $schema):bool
	{
		return $this->db->insert($schema . '.docs', $doc_ary) ? true : false;
	}

	public function update_doc(array $update_ary, int $doc_id, string $schema):bool
	{
		return $this->db->update($schema . '.docs', $update_ary, ['id' => $doc_id]) ? true : false;
	}
}
