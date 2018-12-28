<?php

namespace service;

use Doctrine\DBAL\Connection as db;
use Monolog\Logger;
use service\user_cache;
use service\autominlimit;
use service\config;

class transaction
{
	protected $db;
	protected $monolog;
	protected $user_cache;
	protected $autominlimit;
	protected $config;

	public function __construct(
		db $db,
		Logger $monolog,
		user_cache $user_cache,
		autominlimit $autominlimit,
		config $config
	)
	{
		$this->db = $db;
		$this->monolog = $monolog;
		$this->user_cache = $user_cache;
		$this->autominlimit = $autominlimit;
		$this->config = $config;
	}

	function generate_transid(string $s_id, string $server_name):string
	{
		return substr(sha1($s_id . microtime()), 0, 12) . '_' . $s_id . '@' . $server_name;
	}

	function sign(
		array $transaction,
		string $shared_secret,
		string $schema
	):string
	{
		global $app;

		$amount = (float) $transaction['amount'];
		$amount = $amount * 100;
		$amount = round($amount);
		$to_sign = $shared_secret . $transaction['transid'] . strtolower($transaction['letscode_to']) . $amount;
		$signature = sha1($tosign);
		$this->monolog->debug('Signing ' . $to_sign . ' : ' . $signature,
			['schema' => $schema]);

		return $signature;
	}

	function insert(array $transaction, string $schema):int
	{
		global $app;

		$transaction['creator'] = $app['s_master'] ? 0 : ($app['s_id'] ? $app['s_id'] : 0);
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
			link_user($transaction['id_from'], $schema, false, true) . ' to user ' .
			link_user($transaction['id_to'], $schema, false, true),
			['schema' => $schema]);

		return $id;
	}
}
