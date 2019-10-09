<?php declare(strict_types=1);

namespace App\Task;

use Doctrine\DBAL\Connection as Db;
use App\Service\CacheService;
use App\Service\SystemsService;
use App\Cnst\CacheKeyCnst;

class GetElasIntersystemDomainsTask
{
	protected $cache_service;
	protected $db;
	protected $systems_service;

	public function __construct(
		Db $db,
		CacheService $cache_service,
		SystemsService $systems_service
	)
	{
		$this->db = $db;
		$this->cache_service = $cache_service;
		$this->systems_service = $systems_service;
	}

	function process():void
	{
		$elas_intersystem_domains = $this->cache_service->get(CacheKeyCnst::ELAS_FETCH['domains']);

		$domains = [];

		foreach ($this->systems_service->get_schemas() as $sch)
		{
			$groups = $this->db->fetchAll('select url, remoteapikey, id
				from ' . $sch . '.letsgroups
				where apimethod = \'elassoap\'
					and remoteapikey is not null
					and url <> \'\'');

			foreach ($groups as $group)
			{
				if ($this->systems_service->get_schema_from_legacy_eland_origin($group['url']))
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

		$this->cache_service->set(CacheKeyCnst::ELAS_FETCH['domains'], $domains);

		return;
	}
}
