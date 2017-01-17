<?php

namespace eland\task;

use eland\cache;
use eland\model\task;

use eland\schedule;

class fetch_interlets_users extends task
{
	private $cache;

	public function __construct(cache $cache, schedule $schedule)
	{
		parent::__construct($schedule);
		$this->cache = $cache;
	}

	function process()
	{
		$elas_interlets_domains = $this->cache->get('elas_interlets_domains');

		$domains = [];



		return;
	}

	function get_interval()
	{
		return 900;
	}
}
