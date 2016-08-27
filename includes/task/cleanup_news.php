<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use eland\xdb;

class cleanup_news
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

	function run($schema)
	{
		$now = gmdate('Y-m-d H:i:s');

		$news = $this->db->fetchAll('select id, content from ' . $schema . '.news where itemdate < ? and sticky = \'f\'', [$now]);

		foreach ($news as $n)
		{
			$this->xdb->del('news_access', $n['id'], $schema);
			$this->db->delete($schema . '.news', ['id' => $n['id']]);
			$this->monolog->info('removed news item ' . $n['content'], ['schema' => $schema]);
		}
	}
}
