<?php

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use Monolog\Logger;

use service\schedule;
use service\groups;
use service\this_group;

class saldo_update extends schema_task
{
	private $db;
	private $monolog;

	public function __construct(db $db, Logger $monolog, schedule $schedule, groups $groups, this_group $this_group)
	{
		parent::__construct($schedule, $groups, $this_group);
		$this->db = $db;
		$this->monolog = $monolog;
	}

	function process()
	{
		$user_balances = $min = $plus = [];

		$rs = $this->db->prepare('select id, saldo from ' . $this->schema . '.users');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$user_balances[$row['id']] = $row['saldo'];
		}

		$rs = $this->db->prepare('select id_from, sum(amount)
			from ' . $this->schema . '.transactions
			group by id_from');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$min[$row['id_from']] = $row['sum'];
		}

		$rs = $this->db->prepare('select id_to, sum(amount)
			from ' . $this->schema . '.transactions
			group by id_to');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$plus[$row['id_to']] = $row['sum'];
		}

		foreach ($user_balances as $id => $balance)
		{
			$plus[$id] = $plus[$id] ?? 0;
			$min[$id] = $min[$id] ?? 0;

			$calculated = $plus[$id] - $min[$id];

			if ($balance == $calculated)
			{
				continue;
			}

			$this->db->update($this->schema . '.users', ['saldo' => $calculated], ['id' => $id]);
			$m = 'User id ' . $id . ' balance updated, old: ' . $balance . ', new: ' . $calculated;
			$this->monolog->info('(cron) ' . $m, ['schema' => $this->schema]);
		}
	}

	public function get_interval()
	{
		return 86400;
	}
}
