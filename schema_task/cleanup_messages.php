<?php declare(strict_types=1);

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use Monolog\Logger;

use service\schedule;
use service\systems;
use service\config;

class cleanup_messages extends schema_task
{
	protected $db;
	protected $monolog;
	protected $config;

	public function __construct(
		db $db,
		Logger $monolog,
		schedule $schedule,
		systems $systems,
		config $config
	)
	{
		parent::__construct($schedule, $systems);
		$this->db = $db;
		$this->monolog = $monolog;
		$this->config = $config;
	}

	function process():void
	{
		$msgs = '';
		$testdate = gmdate('Y-m-d H:i:s', time() - $this->config->get('msgexpcleanupdays', $this->schema) * 86400);

		$st = $this->db->prepare('select id, content, id_category, msg_type
			from ' . $this->schema . '.messages
			where validity < ?');

		$st->bindValue(1, $testdate);
		$st->execute();

		while ($row = $st->fetch())
		{
			$msgs .= $row['id'] . ': ' . $row['content'] . ', ';
		}

		$msgs = trim($msgs, '\n\r\t ,;:');

		if ($msgs)
		{
			$this->monolog->info('Expired and deleted Messages ' . $msgs,
				['schema' => $this->schema]);

			$this->db->executeQuery('delete from ' . $this->schema . '.messages
				where validity < ?', [$testdate]);
		}

		$users = '';
		$ids = [];

		$st = $this->db->prepare('select u.id, u.letscode, u.name
			from ' . $this->schema . '.users u, ' . $this->schema . '.messages m
			where u.status = 0
				and m.id_user = u.id');

		$st->execute();

		while ($row = $st->fetch())
		{
			$ids[] = $row['id'];
			$users .= '(id: ' . $row['id'] . ') ' . $row['letscode'] . ' ' . $row['name'] . ', ';
		}
		$users = trim($users, '\n\r\t ,;:');

		if (count($ids))
		{
			$this->monolog->info('Cleanup messages from users: ' . $users,
				['schema' => $this->schema]);

			echo 'Cleanup messages from users: ' . $users;

			if (count($ids) == 1)
			{
				$this->db->delete($this->schema . '.messages',
					['id_user' => $ids[0]]);
			}
			else if (count($ids) > 1)
			{
				$this->db->executeQuery('delete
					from ' . $this->schema . '.messages
					where id_user in (?)',
					[$ids],
					[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
			}
		}

		// update counts for each category

		$offer_count = $want_count = [];

		$rs = $this->db->prepare('select m.id_category, count(m.*)
			from ' . $this->schema . '.messages m, ' .
				$this->schema . '.users u
			where  m.id_user = u.id
				and u.status IN (1, 2, 3)
				and msg_type = 1
			group by m.id_category');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$offer_count[$row['id_category']] = $row['count'];
		}

		$rs = $this->db->prepare('select m.id_category, count(m.*)
			from ' . $this->schema . '.messages m, ' .
				$this->schema . '.users u
			where  m.id_user = u.id
				and u.status IN (1, 2, 3)
				and msg_type = 0
			group by m.id_category');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$want_count[$row['id_category']] = $row['count'];
		}

		$all_cat = $this->db->fetchAll('select id,
				stat_msgs_offers, stat_msgs_wanted
			from ' . $this->schema . '.categories
			where id_parent is not null');

		foreach ($all_cat as $val)
		{
			$offers = $val['stat_msgs_offers'];
			$wants = $val['stat_msgs_wanted'];
			$id = $val['id'];

			$want_count[$id] = $want_count[$id] ?? 0;
			$offer_count[$id] = $offer_count[$id] ?? 0;

			if ($want_count[$id] == $wants && $offer_count[$id] == $offers)
			{
				continue;
			}

			$stats = [
				'stat_msgs_offers'	=> $offer_count[$id] ?? 0,
				'stat_msgs_wanted'	=> $want_count[$id] ?? 0,
			];

			$this->db->update($this->schema . '.categories',
				$stats,
				['id' => $id]);
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
