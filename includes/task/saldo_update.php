<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;

class saldo_update
{
	protected $db;
	protected $monolog;

	public function __construct(db $db, Logger $monolog)
	{
		$this->db = $db;
		$this->monolog = $monolog;
	}

	function run($schema)
	{
		$user_balances = $min = $plus = [];

		$rs = $this->db->prepare('select id, saldo from ' . $schema . '.users');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$user_balances[$row['id']] = $row['saldo'];
		}

		$rs = $this->db->prepare('select id_from, sum(amount)
			from ' . $schema . '.transactions
			group by id_from');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$min[$row['id_from']] = $row['sum'];
		}

		$rs = $this->db->prepare('select id_to, sum(amount)
			from ' . $schema . '.transactions
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

			$this->db->update($schema . '.users', ['saldo' => $calculated], ['id' => $id]);
			$m = 'User id ' . $id . ' balance updated, old: ' . $balance . ', new: ' . $calculated;
			$this->monolog->info('(cron) ' . $m, ['schema' => $schema]);
		}
	}
}
