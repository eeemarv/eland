<?php

namespace task;

use Doctrine\DBAL\Connection as db;
use service\cache;
use service\groups;

class get_elas_intersystem_domains
{
	protected $cache;
	protected $db;
	protected $groups;

	public function __construct(
		db $db,
		cache $cache,
		groups $groups
	)
	{
		$this->db = $db;
		$this->cache = $cache;
		$this->groups = $groups;
	}

	function process():void
	{
		$elas_interlets_domains = $this->cache->get('elas_interlets_domains');

		$domains = [];

		foreach ($this->groups->get_schemas() as $sch)
		{
			$groups = $this->db->fetchAll('select url, remoteapikey, id
				from ' . $sch . '.letsgroups
				where apimethod = \'elassoap\'
					and remoteapikey is not null
					and url <> \'\'');

			foreach ($groups as $group)
			{
				$domain = strtolower(parse_url($group['url'], PHP_URL_HOST));

				if ($this->groups->get_schema($domain))
				{
					continue;
				}

				if (!$group['remoteapikey'])
				{
					continue;
				}

				$domains[$domain][$sch] = [
					'remoteapikey'	=> trim($group['remoteapikey']),
					'group_id'		=> $group['id'],
				];
			}
		}

		error_log('-- get eLAS intersystem domains --');
		error_log(json_encode($elas_interlets_domains));

		if ($elas_interlets_domains == $domains)
		{
			return;
		}

		$this->cache->set('elas_interlets_domains', $domains);

		return;
	}
}
