<?php

namespace schema_task;

use model\schema_task;
use Doctrine\DBAL\Connection as db;
use queue\mail;

use service\schedule;
use service\groups;
use service\this_group;
use service\config;
use service\template_vars;
use service\user_cache;
use service\mail_addr_user;

class user_exp_msgs extends schema_task
{
	protected $db;
	protected $mail;
	protected $protocol;
	protected $config;
	protected $user_cache;
	protected $mail_addr_user;

	public function __construct(db $db, mail $mail, string $protocol,
		schedule $schedule, groups $groups, this_group $this_group, config $config,
		template_vars $template_vars, user_cache $user_cache,
		mail_addr_user $mail_addr_user)
	{
		parent::__construct($schedule, $groups, $this_group);
		$this->db = $db;
		$this->mail = $mail;
		$this->protocol = $protocol;
		$this->config = $config;
		$this->template_vars = $template_vars;
		$this->user_cache = $user_cache;
		$this->mail_addr_user = $mail_addr_user;
	}

	function process():void
	{
		$now = gmdate('Y-m-d H:i:s');

		$base_url = $this->protocol . $this->groups->get_host($this->schema);

		$group_vars = $this->template_vars->get($this->schema);

		$warn_messages  = $this->db->fetchAll('select m.*
			from ' . $this->schema . '.messages m, ' . $this->schema . '.users u
				where m.exp_user_warn = \'f\'
					and u.id = m.id_user
					and u.status in (1, 2)
					and m.validity < ?', [$now]);

		foreach ($warn_messages as $msg)
		{
			$user = $this->user_cache->get($msg['id_user'], $this->schema);

			if (!($user['status'] == 1 || $user['status'] == 2))
			{
				continue;
			}

			$msg['type'] = ($msg['msg_type']) ? 'offer' : 'want';

			$url_extend = [];

			foreach ([7, 30, 60, 180, 365, 730, 1825] as $ext_days)
			{
				$url_extend[$ext_days] = $base_url . '/messages.php?id=' . $msg['id'] . '&extend=' . $ext_days;
			}

			$vars = [
				'msg' 			=> $msg,
				'url_msg'		=> $base_url . '/messages.php?id=' . $msg['id'],
				'user'			=> $user,
				'url_login'		=> $base_url . '/login.php?login=' . $user['letscode'],
				'url_extend' 	=> $url_extend,
				'url_msg_add'	=> $base_url . '/messages.php?add=1',
				'group'			=> $group_vars,
			];

			$this->mail->queue([
				'to' 		=> $this->mail_addr_user->get($msg['id_user'], $this->schema),
				'schema' 	=> $this->schema,
				'template' 	=> 'user_exp_msgs',
				'vars' 		=> $vars]);
		}

		$this->db->executeUpdate('update ' . $this->schema . '.messages
			set exp_user_warn = \'t\'
			where validity < ?', [$now]);
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
