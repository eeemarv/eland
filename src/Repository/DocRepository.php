<?php declare(strict_types=1);

namespace App\Repository;

use App\Service\Xdb;
use App\Service\XdbAccess;
use App\Service\Pagination;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocRepository
{
	private $xdb;
	private $xdbAccess;

	public function __construct(Xdb $xdb, XdbAccess $xdbAccess)
	{
		$this->xdb = $xdb;
		$this->xdbAccess = $xdbAccess;
	}

	public function get(string $id, string $schema):array
	{
		$data = $this->xdb->get('docs', $id, $schema);

		if (!$data)
		{
			throw new NotFoundHttpException(sprintf('Document %s does not exist in %s', 
				$id, __CLASS__));
		}

		return $data;
	}

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
