<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\ItemAccessService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocRepository
{
	protected Db $db;
	protected ItemAccessService $item_access_service;

	public function __construct(
		Db $db,
		ItemAccessService $item_access_service
	)
	{
		$this->db = $db;
		$this->item_access_service = $item_access_service;
	}

	public function get(int $id, string $schema):array
	{
		$doc = $this->db->fetchAssoc('select *
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
		$map =  $this->db->fetchAssoc('select *
			from ' . $schema . '.doc_maps
			where id = ?', [$map_id]);

		if (!$map)
		{
			throw new NotFoundHttpException('Document map ' . $map_id . ' not found.');
		}

		return $map;
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
		return $this->db->fetchColumn('select count(*)
			from ' . $schema . '.docs
			where map_id = ?', [$map_id]);
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

	public function get_visible_maps(string $schema):array
	{
        $stmt = $this->db->executeQuery('select dm.name, dm.id, count(d.*) as doc_count
            from ' . $schema . '.doc_maps dm, ' . $schema . '.docs d
			where d.access in (?)
				and d.map_id = dm.id
			group by dm.name, dm.id
			order by dm.name asc',
            [$this->item_access_service->get_visible_ary_for_page()],
            [Db::PARAM_STR_ARRAY]);

        return $stmt->fetchAll();
	}

	public function get_visible_unmapped_docs($schema):array
	{
		$stmt = $this->db->executeQuery('select coalesce(d.name, d.original_filename) as name,
				id, filename, access, created_at
            from ' . $schema . '.docs d
			where d.access in (?)
				and map_id is null
			order by name asc',
            [$this->item_access_service->get_visible_ary_for_page()],
            [Db::PARAM_STR_ARRAY]);

        return $stmt->fetchAll();
	}
}
