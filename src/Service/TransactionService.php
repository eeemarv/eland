<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\AutoMinLimitService;
use App\Service\ConfigService;
use App\Render\AccountRender;

class TransactionService
{
	protected Db $db;
	protected LoggerInterface $logger;
	protected AutoMinLimitService $autominlimit_service;
	protected ConfigService $config_service;
	protected AccountRender $account_render;

	public function __construct(
		Db $db,
		LoggerInterface $logger,
		AutoMinLimitService $autominlimit_service,
		ConfigService $config_service,
		AccountRender $account_render
	)
	{
		$this->db = $db;
		$this->logger = $logger;
		$this->autominlimit_service = $autominlimit_service;
		$this->config_service = $config_service;
		$this->account_render = $account_render;
	}

	public function generate_transid(int $s_id, string $system_name):string
	{
		$transid = substr(sha1(random_bytes(16)), 0, 12);
		$transid .= '_';
		$transid .= (string) $s_id;
		$transid .= '@' . $system_name;
		return $transid;
	}

	public function insert(array $transaction, string $schema):int
	{
		$from_id = (int) $transaction['id_from'];
		$to_id = (int) $transaction['id_to'];
		$amount = (int) $transaction['amount'];

		$this->db->beginTransaction();

		$this->db->insert($schema . '.transactions', $transaction);
		$id = (int) $this->db->lastInsertId($schema . '.transactions_id_seq');

		$this->db->executeUpdate('insert into ' . $schema . '.balance (account_id, amount, balance)
			values (?, ?, (select coalesce(balance, 0) + ? from ' . $schema . '.balance
			where account_id = ?
			order by id desc limit 1))',
			[$to_id, $amount, $amount, $to_id],
			[\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]);

		$this->db->executeUpdate('insert into ' . $schema . '.balance (account_id, amount, balance)
			values (?, 0 - ?, (select coalesce(balance, 0) - ? from ' . $schema . '.balance
			where account_id = ?
			order by id desc limit 1))',
			[$from_id, $amount, $amount, $from_id],
			[\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]);

		$this->db->commit();

		$this->autominlimit_service->init($schema)
			->process($from_id, $to_id, $amount);

		$this->logger->info('Transaction ' . $transaction['transid'] . ' saved: ' .
			$amount . ' ' .
			$this->config_service->get('currency', $schema) .
			' from user ' .
			$this->account_render->str_id($from_id, $schema) .
			' to user ' .
			$this->account_render->str_id($to_id, $schema),
			['schema' => $schema]);

		return $id;
	}
}
