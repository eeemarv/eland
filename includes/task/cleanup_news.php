<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use eland\xdb;

class cleanup_news
{
	protected $db;
	protected $xdb;

	public function __construct(db $db, xdb $sdb)
	{
		$this->db = $db;
		$this->xdb = $xdb;
	}

	function run()
	{
		$now = gmdate('Y-m-d H:i:s');

		$news = $this->db->fetchAll('select id from news where itemdate < ? and sticky = \'f\'', [$now]);

		foreach ($news as $n)
		{
			$this->xdb->del('news_access', $n['id']);
			$this->db->delete('news', ['id' => $n['id']]);
		}
	}
}
