<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocRepository
{
	protected Db $db;

	public function __construct(
		Db $db
	)
	{
		$this->db = $db;
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



	/*********** */

	public function mapNameExists(string $mapName, string $schema, string $exceptId = ''):bool
	{
		$param = ['agg_schema' => $schema,
			'agg_type' => 'doc',
			'data->>\'map_name\'' => $mapName
		];

		if ($exceptId !== '')
		{
			$param['eland_id'] = ['<>' => $exceptId];
		}

		return $this->xdb->countFiltered($param) > 0;
	}

	public function getMapName(string $id, string $schema):string
	{
		$row = $this->xdb->get('doc', $id, $schema);

		if (!isset($row['data']['map_name']))
		{
			throw new NotFoundHttpException(sprintf('Could not find map %s', $id));
		}

		return $row['data']['map_name'];
	}

	public function getAllMaps(string $schema, string $access):array
	{
		$param = ['agg_type' => 'doc',
			'agg_schema' => $schema,
			'data->>\'map_name\'' => ['<>' => '']];

		$allMaps = $this->xdb->getFilteredData($param, 'order by data->>\'map_name\' asc');

		if ($access === 'a')
		{
			return $allMaps;
		}

		$maps = [];

		$accessAry = $this->xdbAccess->get($access);

		foreach ($allMaps as $aggId => $map)
		{
			if ($this->xdb->getFilteredCount([
				'agg_schema' => $schema,
				'data->>\'map_id\'' => $map['id'],
				'data->>\'access\'' => $accessAry]) > 0)
			{
				$maps[$aggId] = $map;
			}
		}

		return $maps;
	}

	public function getAll(string $schema, string $access, string $mapId = ''):array
	{
		$param = ['agg_type' => 'doc',
			'access' => $this->xdbAccess->get($access),
			'agg_schema' => $schema,
		];

		if ($mapId === '')
		{
			$param['data->>\'map_name\''] = ['is null'];
		}
		else
		{
			$param['data->>\'map_id\''] = $mapId;
		}

		return $this->xdb->getFilteredData($param, 'order by event_time asc');
	}

	public function upsertMap(string $mapName, string $schema):string
	{
		$rows = $this->xdb->getFiltered(['agg_type' => 'doc',
			'agg_schema' => $schema,
			'data->>\'map_name\'' => $mapName], 'limit 1');

		if (count($rows))
		{
			$map = reset($rows)['data'];
			$mapId = reset($rows)['eland_id'];
		}
		else
		{
			$map = ['map_name' => $map_name];

			$mapId = substr(sha1(microtime() . $app['this_group']->get_schema() . $mapName), 0, 24);

			$this->xdb->set('doc', $mapId, $schema, $map);
		}

		return $mapId;
	}
}
