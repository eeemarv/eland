<?php

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use queue\mail;

use service\schedule;
use service\systems;
use service\config;
use service\user_cache;
use service\mail_addr_user;

class user_exp_msgs extends schema_task
{
	protected $db;
	protected $mail;
	protected $config;
	protected $user_cache;
	protected $mail_addr_user;

	public function __construct(
		db $db,
		mail $mail,
		schedule $schedule,
		systems $systems,
		config $config,
		user_cache $user_cache,
		mail_addr_user $mail_addr_user
	)
	{
		parent::__construct($schedule, $systems);
		$this->db = $db;
		$this->mail = $mail;
		$this->config = $config;
		$this->user_cache = $user_cache;
		$this->mail_addr_user = $mail_addr_user;
	}

	function process(bool $update = true):void
	{
		$now = gmdate('Y-m-d H:i:s');

		$warn_messages  = $this->db->fetchAll('select m.*
			from ' . $this->schema . '.messages m, ' .
				$this->schema . '.users u
			where m.exp_user_warn = \'f\'
				and u.id = m.id_user
				and u.status in (1, 2)
				and m.validity < ?', [$now]);

		foreach ($warn_messages as $message)
		{
			$user = $this->user_cache->get($message['id_user'], $this->schema);

			if (!($user['status'] == 1 || $user['status'] == 2))
			{
				continue;
			}

			$message['type'] = $message['msg_type'] ? 'offer' : 'want';

			$vars = [
				'message' 		=> $message,
				'user_id'		=> $user['id'],
			];

			$mail_template = 'message_extend/';
			$mail_template .= $message['type'] === 'offer' ? 'offer' : 'want';

			$this->mail->queue([
				'to' 				=> $this->mail_addr_user->get_active($message['id_user'], $this->schema),
				'schema' 			=> $this->schema,
				'template' 			=> $mail_template,
				'vars' 				=> $vars
			], random_int(0, 5000));

			error_log($mail_template);
			error_log(json_encode($vars));
		}

		if ($update)
		{
			$this->db->executeUpdate('update ' . $this->schema . '.messages
				set exp_user_warn = \'t\'
				where validity < ?', [$now]);
		}
	}

	public function is_enabled():bool
	{
		return $this->config->get('msgexpwarnenabled', $this->schema) ? true : false;
	}

	public function get_interval():int
	{
		return 86400;
	}
}
