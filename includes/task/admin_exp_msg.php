<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use eland\task\mail;
use eland\groups;

class admin_exp_msg
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
		$host = $this->groups->get_host($schema);

		if (!$host)
		{
			return;
		}

		$base_url = $this->protocol . $host;

		$now = gmdate('Y-m-d H:i:s');

		$query = 'select m.id_user, m.content, m.id
			from ' . $schema . '.messages m, ' . $schema . '.users u
			where u.status in (1, 2)
				and m.id_user = u.id
				and validity <= ?';

		$msgs = $this->db->fetchAll($query, [$now]);

		$messages = [];

		foreach($msgs as $msg)
		{
			$messages[] = [
				'user_str'	=> link_user($msg['id_user'], $schema, false),
				'user_url'	=> $base_url . '/users.php?id=' . $msg['id_user'],
				'content'	=> $msg['content'],
				'url'		=> $base_url . '/messages.php?id=' . $msg['id'],
			];
		}

		$vars = [
			'messages'	=> $messages,
			'group'		=> $this->groups->get_template_vars($schema),
		];

		$this->mail->queue(['to' => 'admin',
			'template'	=> 'admin_exp_msg',
			'vars'		=> $vars,
			'schema' 	=> $schema]);
	}
}
