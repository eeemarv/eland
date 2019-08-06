<?php declare(strict_types=1);

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use service\xdb;

use service\schedule;
use service\systems;

class cleanup_news extends schema_task
{
	protected $db;
	protected $xdb;
	protected $monolog;

	public function __construct(
		db $db,
		xdb $xdb,
		Logger $monolog,
		schedule $schedule,
		systems $systems
	)
	{
		parent::__construct($schedule, $systems);
		$this->db = $db;
		$this->xdb = $xdb;
		$this->monolog = $monolog;
	}

	public function process():void
	{
		$now = gmdate('Y-m-d H:i:s');

		$news = $this->db->fetchAll('select id, headline
			from ' . $this->schema . '.news
			where itemdate < ?
				and sticky = \'f\'', [$now]);

		foreach ($news as $n)
		{
			$this->xdb->del('news_access', (string) $n['id'], $this->schema);
			$this->db->delete($this->schema . '.news', ['id' => $n['id']]);
			$this->monolog->info('removed news item ' . $n['headline'],
				['schema' => $this->schema]);
		}
	}

	public function is_enabled():bool
	{
		return true;
	}

	public function get_interval():int
	{
		return 86400;
	}
}
