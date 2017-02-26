<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use eland\cache;
use eland\model\task;
use eland\groups;

use eland\schedule;

class get_elas_interlets_domains extends task
{
	private $cache;
	private $db;
	private $groups;

	public function __construct(db $db, cache $cache, schedule $schedule, groups $groups)
	{
		parent::__construct($schedule);
		$this->db = $db;
		$this->cache = $cache;
		$this->groups = $groups;
	}

	function process()
	{
		$elas_interlets_domains = $this->cache->get('elas_interlets_domains');

		$domains = [];

		foreach ($this->groups->get_schemas() as $sch)
		{
			$groups = $this->db->fetchAll('select url, remoteapikey
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

				$domains[$domain][$sch] = trim($group['remoteapikey']);
			}
		}

		if ($elas_interlets_domains == $domains)
		{
			return;
		}

		$this->cache->set('elas_interlets_domains', $domains);

		error_log('update domains: ' . json_encode($domains));

		return;
	}

	function get_interval()
	{
		return 60;
	}
}
