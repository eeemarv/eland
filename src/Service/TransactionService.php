<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;
use App\Service\UserCache;
use App\Service\AutoMinLimit;
use App\Service\Config;
use App\Render\AccountRender;

class TransactionService
{
	protected $db;
	protected $logger;
	protected $user_cache;
	protected $autominlimit;
	protected $config;
	protected $account_render;

	public function __construct(
		Db $db,
		LoggerInterface $logger,
		UserCache $user_cache,
		AutoMinLimit $autominlimit,
		Config $config,
		AccountRender $account_render
	)
	{
		$this->db = $db;
		$this->logger = $logger;
		$this->user_cache = $user_cache;
		$this->autominlimit = $autominlimit;
		$this->config = $config;
		$this->account_render = $account_render;
	}

	public function generate_transid(int $s_id, string $system_name):string
	{
		$transid = substr(sha1($s_id . microtime()), 0, 12);
		$transid .= '_';
		$transid .= (string) $s_id;
		$transid .= '@' . $system_name;
		return $transid;
	}

	public function sign(
		array $transaction,
		string $shared_secret,
		string $schema
	):string
	{
		$amount = (float) $transaction['amount'];
		$amount = $amount * 100;
		$amount = round($amount);
		$to_sign = $shared_secret . $transaction['transid'] . strtolower($transaction['letscode_to']) . $amount;
		$signature = sha1($to_sign);
		$this->logger->debug('Signing ' . $to_sign . ' : ' . $signature,
			['schema' => $schema]);

		return $signature;
	}

	public function insert(array $transaction, string $schema):int
	{
		$transaction['cdate'] = gmdate('Y-m-d H:i:s');

		$this->db->beginTransaction();

		try
		{
			$this->db->insert($schema . '.transactions', $transaction);
			$id = (int) $this->db->lastInsertId($schema . '.transactions_id_seq');
			$this->db->executeUpdate('update ' . $schema . '.users set saldo = saldo + ? where id = ?', [$transaction['amount'], $transaction['id_to']]);
			$this->db->executeUpdate('update ' . $schema . '.users set saldo = saldo - ? where id = ?', [$transaction['amount'], $transaction['id_from']]);
			$this->db->commit();

		}
		catch(Exception $e)
		{
			$this->db->rollback();
			throw $e;
			return 0;
		}

		$this->user_cache->clear($transaction['id_to'], $schema);
		$this->user_cache->clear($transaction['id_from'], $schema);

		$this->autominlimit->init($schema)
			->process($transaction['id_from'],
				$transaction['id_to'],
				(int) $transaction['amount']);

		$this->logger->info('Transaction ' . $transaction['transid'] . ' saved: ' .
			$transaction['amount'] . ' ' .
			$this->config->get('currency', $schema) .
			' from user ' .
			$this->account_render->str_id($transaction['id_from'], $schema) .
			' to user ' .
			$this->account_render->str_id($transaction['id_to'], $schema),
			['schema' => $schema]);

		return $id;
	}
}
