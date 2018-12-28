<?php

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

	public function mail_mailtype_interlets_transaction(array $transaction):void
	{
		global $app;

		$from_user = link_user($transaction['id_from'], $app['tschema'], false);
		$to_group = link_user($transaction['id_to'], $app['tschema'], false);

		$to_user = $transaction['real_to'];

		$vars = [
			'support_url'	=> $app['base_url'] . '/support.php?src=p',
			'copy'			=> false,
			'from_user' 	=> $from_user,
			'to_user'		=> $to_user,
			'to_group'		=> $to_group,
			'amount'		=> $transaction['amount'],
			'amount_hours'	=> round($transaction['amount'] / $this->config->get('currencyratio', $app['tschema']), 4),
			'transid'		=> $transaction['transid'],
			'description'	=> $transaction['description'],
		];

		$this->mail->queue([
			'schema'	=> $app['tschema'],
			'to' 		=> $this->mail_addr_user->get($transaction['id_to'], $app['tschema']),
			'reply_to' 	=> $this->mail_addr_system->get_admin($app['tschema']),
			'template'	=> 'mailtype_interlets_transaction',
			'vars'		=> $vars,
		], 9000);

		$vars['copy'] = true;

		$this->mail->queue([
			'schema'	=> $app['tschema'],
			'to' 		=> $this->mail_addr_user->get($transaction['id_from'], $app['tschema']),
			'cc' 		=> $this->mail_addr_system->get_admin($app['tschema']),
			'template'	=> 'mailtype_interlets_transaction',
			'vars'		=> $vars,
		], 9000);
	}

	public function mail_transaction_____(array $transaction, $remote_schema = null)
	{
		global $app;

		$sch = isset($remote_schema) ? $remote_schema : $app['tschema'];

		$userfrom = $this->user_cache->get($transaction['id_from'], $sch);
		$userto = $this->user_cache->get($transaction['id_to'], $sch);

		$interlets = ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? 'interlets ' : '';

		$real_from = $transaction['real_from'] ?? '';
		$real_to = $transaction['real_to'] ?? '';

		$from_user = $real_from ? $real_from . ' [' . $userfrom['fullname'] . ']' : $userfrom['letscode'] . ' ' . $userfrom['name'];
		$to_user = $real_to ? $real_to . ' [' . $userto['fullname'] . ']' : $userto['letscode'] . ' ' . $userto['name'];

		$url = isset($remote_schema) ? $app['protocol'] . $app['groups']->get_host($sch) : $app['base_url'];

		$vars = [
			'support_url'		=> $url . '/support.php?src=p',
			'from_user' 		=> $from_user,
			'to_user'			=> $to_user,
			'interlets'			=> ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? true : false,
			'amount'			=> $transaction['amount'],
			'trans_id'			=> $transaction['transid'],
			'description'		=> $transaction['description'],
			'transaction_url'	=> $url . '/transactions.php?id=' . $transaction['id'],
			'group'				=> $app['template_vars']->get($sch),
		];

		$t_schema = $remote_schema ? $remote_schema . '.' : '';

		$base_url = $app['protocol'] . $app['groups']->get_host($sch);

		if ($userfrom['accountrole'] != 'interlets'
			&& ($userfrom['status'] == 1
				|| $userfrom['status'] == 2))
		{
			$this->mail->queue([
				'schema'	=> $app['tschema'],
				'to' 		=> $this->mail_addr_user->get($userfrom['id'], $app['tschema']),
				'template'	=> 'transaction',
				'vars'		=> array_merge($vars, [
					'user' 			=> $userfrom,
					'login_url'		=> $base_url . '/login.php?login=' . $userfrom['letscode'],
				]),
			], 9000);
		}

		if ($userto['accountrole'] != 'interlets'
			&& ($userto['status'] == 1
				|| $userto['status'] == 2))
		{
			$this->mail->queue([
				'to' 		=> $this->mail_addr_user->get($userto['id'], $sch),
				'schema'	=> $sch,
				'template'	=> 'transaction',
				'vars'		=> array_merge($vars, [
					'user'		=> $userto,
					'login_url'	=> $base_url . '/login.php?login=' . $userto['letscode'],
				]),
			], 9000);
		}
	}

	public function mail_transaction(array $transaction):void
	{
		global $app;

		$from_user_id = $transaction['id_from'];
		$to_user_id = $transaction['id_to'];

		$from_user = $this->user_cache->get($from_user_id, $app['tschema']);
		$to_user = $this->user_cache->get($to_user_id, $app['tschema']);

		$url = isset($remote_schema) ? $app['protocol'] . $app['groups']->get_host($sch) : $app['base_url'];

		$vars = [
			'support_url'		=> $url . '/support.php?src=p',
			'from_user_id' 		=> $from_user_id,
			'to_user_id'		=> $to_user_id,
			'amount'			=> $transaction['amount'],
			'trans_id'			=> $transaction['transid'],
			'description'		=> $transaction['description'],
			'transaction_url'	=> $app['base_url'] . '/transactions.php?id=' . $transaction['id'],
		];

		if ($from_user['accountrole'] != 'interlets'
			&& ($from_user['status'] == 1
				|| $from_user['status'] == 2))
		{
			$this->mail->queue([
				'schema'	=> $app['tschema'],
				'to' 		=> $this->mail_addr_user->get($from_user_id, $app['tschema']),
				'template'	=> 'transaction',
				'vars'		=> array_merge($vars, [
					'user' 			=> $from_user,
					'login_url'		=> $app['base_url'] . '/login.php?login=' . $from_user['letscode'],
				]),
			], 9000);
		}

		if ($to_user['accountrole'] != 'interlets'
			&& ($to_user['status'] == 1
				|| $to_user['status'] == 2))
		{
			$this->mail->queue([
				'to' 		=> $this->mail_addr_user->get($to_user_id, $app['tschema']),
				'schema'	=> $app['tschema'],
				'template'	=> 'transaction',
				'vars'		=> array_merge($vars, [
					'user'		=> $to_user,
					'login_url'	=> $app['base_url'] . '/login.php?login=' . $to_user['letscode'],
				]),
			], 9000);
		}
	}
}
