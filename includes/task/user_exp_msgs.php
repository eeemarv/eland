<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use eland\task\mail;
use eland\groups;

class user_exp_msgs
{
	protected $db;
	protected $mail;
	protected $groups;
	protected $protocol;

	public function __construct(db $db, mail $mail, groups $groups, string $protocol)
	{
		$this->db = $db;
		$this->mail = $mail;
		$this->groups = $groups;
		$this->protocol = $protocol;
	}

	function run($schema)
	{
		$now = gmdate('Y-m-d H:i:s');

		$base_url = $this->protocol . $this->groups->get_host($schema);

		$group_vars = $this->groups->get_template_vars($schema);

		$warn_messages  = $this->db->fetchAll('select m.*
			from ' . $schema . '.messages m, ' . $schema . '.users u
				where m.exp_user_warn = \'f\'
					and u.id = m.id_user
					and u.status in (1, 2)
					and m.validity < ?', [$now]);

		foreach ($warn_messages as $msg)
		{
			$user = readuser($msg['id_user'], $schema);

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

			$this->mail->queue(['to' => $msg['id_user'],
				'schema' 	=> $schema,
				'template' 	=> 'user_exp_msgs',
				'vars' 		=> $vars]);
		}

		$this->db->executeUpdate('update ' . $schema . '.messages set exp_user_warn = \'t\' WHERE validity < ?', [$now]);
	}
}
