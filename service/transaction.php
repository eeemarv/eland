<?php

namespace service;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use service\user_cache;
use service\autominlimit;
use service\config;
use render\account;

class transaction
{
	protected $db;
	protected $monolog;
	protected $user_cache;
	protected $autominlimit;
	protected $config;
	protected $account;

	public function __construct(
		db $db,
		Logger $monolog,
		user_cache $user_cache,
		autominlimit $autominlimit,
		config $config,
		account $account
	)
	{
		$this->db = $db;
		$this->monolog = $monolog;
		$this->user_cache = $user_cache;
		$this->autominlimit = $autominlimit;
		$this->config = $config;
		$this->account = $account;
	}

	public function generate_transid(string $s_id, string $system_name):string
	{
		return substr(sha1($s_id . microtime()), 0, 12) . '_' . $s_id . '@' . $system_name;
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
		$this->monolog->debug('Signing ' . $to_sign . ' : ' . $signature,
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
			$id = $this->db->lastInsertId($schema . '.transactions_id_seq');
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
			$transaction['amount']);

		$this->monolog->info('Transaction ' . $transaction['transid'] . ' saved: ' .
			$transaction['amount'] . ' ' .
			$this->config->get('currency', $schema) .
			' from user ' .
			$this->account->str_id($transaction['id_from'], $schema) .
			' to user ' .
			$this->account->str_id($transaction['id_to'], $schema),
			['schema' => $schema]);

		return $id;
	}
}
