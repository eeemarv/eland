<?php

namespace eland\task;

use eland\cache;
use eland\model\task;

use eland\schedule;

class cleanup_cache extends task
{
	private $cache;

	public function __construct(cache $cache, schedule $schedule)
	{
		parent::__construct($schedule);
		$this->cache = $cache;
	}

	function process()
	{
		$this->cache->cleanup();
	}

	function get_interval()
	{
		return 7200;
	}
}
