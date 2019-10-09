<?php declare(strict_types=1);

namespace App\Task;

use Doctrine\DBAL\Connection as Db;
use App\Service\Cache;
use App\Service\Systems;
use App\Cnst\CacheKeyCnst;

class GetElasIntersystemDomainsTask
{
	protected $cache;
	protected $db;
	protected $systems;

	public function __construct(
		Db $db,
		Cache $cache,
		Systems $systems
	)
	{
		$this->db = $db;
		$this->cache = $cache;
		$this->systems = $systems;
	}

	function process():void
	{
		$elas_intersystem_domains = $this->cache->get(CacheKeyCnst::ELAS_FETCH['domains']);

		$domains = [];

		foreach ($this->systems->get_schemas() as $sch)
		{
			$groups = $this->db->fetchAll('select url, remoteapikey, id
				from ' . $sch . '.letsgroups
				where apimethod = \'elassoap\'
					and remoteapikey is not null
					and url <> \'\'');

			foreach ($groups as $group)
			{
				if ($this->systems->get_schema_from_legacy_eland_origin($group['url']))
				{
					continue;
				}

				if (!$group['remoteapikey'])
				{
					continue;
				}

				$domain = strtolower(parse_url($group['url'], PHP_URL_HOST));

				$domains[$domain][$sch] = [
					'remoteapikey'	=> trim($group['remoteapikey']),
					'group_id'		=> $group['id'],
				];
			}
		}

		if ($elas_intersystem_domains == $domains)
		{
			return;
		}

		$this->cache->set(CacheKeyCnst::ELAS_FETCH['domains'], $domains);

		return;
	}
}
