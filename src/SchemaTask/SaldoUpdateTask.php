<?php declare(strict_types=1);

namespace App\SchemaTask;

use App\Model\SchemaTask;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

use App\Service\Schedule;
use App\Service\Systems;

class SaldoUpdateTask extends SchemaTask
{
	protected $db;
	protected $logger;

	public function __construct(
		Db $db,
		LoggerInterface $logger,
		Schedule $schedule,
		Systems $systems
	)
	{
		parent::__construct($schedule, $systems);
		$this->db = $db;
		$this->logger = $logger;
	}

	function process():void
	{
		$user_balances = $min = $plus = [];

		$rs = $this->db->prepare('select id, saldo
			from ' . $this->schema . '.users');

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

			$this->db->update($this->schema . '.users',
				['saldo' => $calculated], ['id' => $id]);

			$m = 'User id ' . $id . ' balance updated, old: ' .
				$balance . ', new: ' . $calculated;
			$this->logger->info('(cron) ' . $m, ['schema' => $this->schema]);
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
