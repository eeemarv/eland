<?php declare(strict_types=1);

namespace App\Service;

use App\Service\UserCacheService;
use App\Service\ConfigService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Queue\MailQueue;

class MailTransactionService
{
	protected $user_cache_service;
	protected $config_service;
	protected $mail_addr_system_service;
	protected $mail_addr_user_service;
	protected $mail_queue;

	public function __construct(
		UserCacheService $user_cache_service,
		ConfigService $config_service,
		MailAddrSystemService $mail_addr_system_service,
		MailAddrUserService $mail_addr_user_service,
		MailQueue $mail_queue
	)
	{
		$this->user_cache_service = $user_cache_service;
		$this->config_service = $config_service;
		$this->mail_addr_system_service = $mail_addr_system_service;
		$this->mail_addr_user_service = $mail_addr_user_service;
		$this->mail_queue = $mail_queue;
	}

	public function queue_mail_type(
		array $transaction,
		string $schema
	):void
	{
		$dec_hours = $transaction['amount'] / $this->config_service->get('currencyratio', $schema);
		$seconds = $dec_hours * 3600;
		$hours = floor($dec_hours);
		$seconds -= $hours * 3600;
		$minutes = floor($seconds / 60);
		$seconds -= $minutes * 60;
		$seconds = round($seconds);
		$dec_hours = round($dec_hours, 4);

		$from_user_id = $transaction['id_from'];
		$to_user_id = $transaction['id_to'];

		$vars = [
			'from_user_id' 	=> $from_user_id,
			'to_user_id'	=> $to_user_id,
			'transaction'	=> $transaction,
			'amount_time'	=> [
				'dec_hours'	=> $dec_hours,
				'hours'		=> $hours,
				'minutes'	=> $minutes,
				'seconds'	=> $seconds,
			],
		];

		$this->mail_queue->queue([
			'schema'	=> $schema,
			'to' 		=> $this->mail_addr_user_service->get_active($transaction['id_to'], $schema),
			'reply_to' 	=> $this->mail_addr_system_service->get_admin($schema),
			'template'	=> 'transaction/to_intersystem_mail_type',
			'vars'		=> array_merge($vars, [
				'user_id'	=> $to_user_id,
			]),
		], 9000);

		$this->mail_queue->queue([
			'schema'	=> $schema,
			'to' 		=> $this->mail_addr_system_service->get_admin($schema),
			'template'	=> 'transaction/to_intersystem_mail_type_admin',
			'vars'		=> $vars,
		], 9000);

		$this->mail_queue->queue([
			'schema'	=> $schema,
			'to' 		=> $this->mail_addr_system_service->get_admin($schema),
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

		$from_user = $this->user_cache_service->get($from_user_id, $schema);
		$to_user = $this->user_cache_service->get($to_user_id, $schema);

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

			$this->mail_queue->queue([
				'schema'	=> $schema,
				'to' 		=> $this->mail_addr_user_service->get_active($from_user_id, $schema),
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

			$this->mail_queue->queue([
				'to' 		=> $this->mail_addr_user_service->get_active($to_user_id, $schema),
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
