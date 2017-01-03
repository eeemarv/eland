<?php

namespace eland\task;

use eland\model\task;
use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use eland\xdb;

class cleanup_news extends task
{
	protected $db;
	protected $xdb;
	protected $monolog;

	public function __construct(db $db, xdb $xdb, Logger $monolog)
	{
		$this->db = $db;
		$this->xdb = $xdb;
		$this->monolog = $monolog;
	}

	public function run()
	{
		$now = gmdate('Y-m-d H:i:s');

		$news = $this->db->fetchAll('select id, headline from ' . $this->schema . '.news where itemdate < ? and sticky = \'f\'', [$now]);

		foreach ($news as $n)
		{
			$this->xdb->del('news_access', $n['id'], $this->schema);
			$this->db->delete($this->schema . '.news', ['id' => $n['id']]);
			$this->monolog->info('removed news item ' . $n['headline'], ['schema' => $this->schema]);
		}
	}

	public function get_interval()
	{
		return 86400;
	}
}
