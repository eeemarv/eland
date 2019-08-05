<?php declare(strict_types=1);

namespace service;

use service\user_cache;
use service\config;
use service\mail_addr_system;
use service\mail_addr_user;
use queue\mail;

class mail_transaction
{
	protected $user_cache;
	protected $config;
	protected $mail_addr_system;
	protected $mail_addr_user;
	protected $mail;

	public function __construct(
		user_cache $user_cache,
		config $config,
		mail_addr_system $mail_addr_system,
		mail_addr_user $mail_addr_user,
		mail $mail
	)
	{
		$this->user_cache = $user_cache;
		$this->config = $config;
		$this->mail_addr_system = $mail_addr_system;
		$this->mail_addr_user = $mail_addr_user;
		$this->mail = $mail;
	}

	public function queue_mail_type(
		array $transaction,
		string $schema
	):void
	{
		$dec_hours = $transaction['amount'] / $this->config->get('currencyratio', $schema);
		$seconds = $dec_hours * 3600;
		$hours = floor($dec_hours);
		$seconds -= $hours * 3600;
		$minutes = floor($seconds / 60);
		$seconds -= $minutes * 60;
		$seconds = round($seconds);
		$dec_hours = round($dec_hours, 4);

		$from_user_id = $transaction['id_from'];
		$to_user_id = $transaction['id_to'];

		$from_user = $this->user_cache->get($from_user_id, $schema);
		$to_user = $this->user_cache->get($to_user_id, $schema);

		$vars = [
			'from_user_id' 	=> $from_user_id,
			'to_user_id'	=> $to_user_id,
			'to_fullname'	=> $to_user['fullname'],
			'transaction'	=> $transaction,
			'amount_time'	=> [
				'dec_hours'	=> $dec_hours,
				'hours'		=> $hours,
				'minutes'	=> $minutes,
				'seconds'	=> $seconds,
			],
		];

		$this->mail->queue([
			'schema'	=> $schema,
			'to' 		=> $this->mail_addr_user->get($transaction['id_to'], $schema),
			'reply_to' 	=> $this->mail_addr_system->get_admin($schema),
			'template'	=> 'transaction/to_intersystem_mail_type',
			'vars'		=> array_merge($vars, [
				'user_id'	=> $to_user_id,
			]),
		], 9000);

		$this->mail->queue([
			'schema'	=> $schema,
			'to' 		=> $this->mail_addr_system->get_admin($schema),
			'template'	=> 'transaction/to_intersystem_mail_type_admin',
			'vars'		=> $vars,
		], 9000);

		$this->mail->queue([
			'schema'	=> $schema,
			'to' 		=> $this->mail_addr_system->get_admin($schema),
			'template'	=> 'transaction/to_intersystem_mail_type_user',
			'vars'		=> array_merge($vars, [
				'user_id'	=> $from_user_id,
			]),
		], 9010);
	}

	public function queue(
		array $transaction,
		string $schema
	):void
	{
		$from_user_id = $transaction['id_from'];
		$to_user_id = $transaction['id_to'];

		$from_user = $this->user_cache->get($from_user_id, $schema);
		$to_user = $this->user_cache->get($to_user_id, $schema);

		$vars = [
			'from_user_id' 		=> $from_user_id,
			'to_user_id'		=> $to_user_id,
			'transaction'		=> $transaction,
		];

		if ($from_user['accountrole'] != 'interlets'
			&& ($from_user['status'] == 1
				|| $from_user['status'] == 2))
		{
			$tpl = 'transaction/';
			$tpl .= $to_user['accountrole'] == 'interlets' ? 'to_intersystem' : 'transaction';

			$this->mail->queue([
				'schema'	=> $schema,
				'to' 		=> $this->mail_addr_user->get($from_user_id, $schema),
				'template'	=> $tpl,
				'vars'		=> array_merge($vars, [
					'user_id' 		=> $from_user_id,
					'to_fullname'	=> $to_user['fullname'],
				]),
			], 9000);
		}

		if ($to_user['accountrole'] != 'interlets'
			&& ($to_user['status'] == 1
				|| $to_user['status'] == 2))
		{
			$tpl = 'transaction/';
			$tpl .= $from_user['accountrole'] == 'interlets' ? 'from_intersystem' : 'transaction';

			$this->mail->queue([
				'to' 		=> $this->mail_addr_user->get($to_user_id, $schema),
				'schema'	=> $schema,
				'template'	=> $tpl,
				'vars'		=> array_merge($vars, [
					'user_id'		=> $to_user_id,
					'from_fullname'	=> $from_user['fullname'],
				]),
			], 9000);
		}
	}
}
