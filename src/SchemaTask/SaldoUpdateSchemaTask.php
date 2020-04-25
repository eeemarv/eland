<?php declare(strict_types=1);

namespace App\SchemaTask;

use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class BalanceUpdateSchemaTask implements SchemaTaskInterface
{
	protected Db $db;
	protected LoggerInterface $logger;

	public function __construct(
		Db $db,
		LoggerInterface $logger
	)
	{
		$this->db = $db;
		$this->logger = $logger;
	}

	public static function get_default_index_name():string
	{
		return 'balance_update';
	}

	public function run(string $schema, bool $update):void
	{
		$user_balances = $min = $plus = [];

		$rs = $this->db->prepare('select id, balance
			from ' . $schema . '.users');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$user_balances[$row['id']] = $row['balance'];
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

			$this->db->update($schema . '.users',
				['balance' => $calculated], ['id' => $id]);

			$m = 'User id ' . $id . ' balance updated, old: ' .
				$balance . ', new: ' . $calculated;
			$this->logger->info('(cron) ' . $m, ['schema' => $schema]);
		}
	}

	public function is_enabled(string $schema):bool
	{
		return true;
	}

	public function get_interval(string $schema):int
	{
		return 86400;
	}
}
