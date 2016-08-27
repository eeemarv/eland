<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use eland\task\mail;
use eland\groups;

class admin_exp_msg
{
	protected $db;
	protected $mail;
	protected $protocol;

	public function __construct(db $db, mail $mail, groups $groups, string $protocol)
	{
		$this->db = $db;
		$this->mail = $mail;
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

		$query = 'SELECT m.id_user, m.content, m.id, to_char(m.validity, \'YYYY-MM-DD\') as vali
			FROM ' . $schema . '.messages m, ' . $schema . '.users u
			WHERE u.status <> 0
				AND m.id_user = u.id
				AND validity <= ?';

		$messages = $this->db->fetchAll($query, [$now]);

		$subject = 'Rapport vervallen Vraag en aanbod';

		$text = "-- Dit is een automatische mail, niet beantwoorden aub --\n\n";
		$text .= "Gebruiker\t\tVervallen vraag of aanbod\t\tVervallen\n\n";
		
		foreach($messages as $key => $value)
		{
			$text .= link_user($value['id_user'], $schema, false) . "\t\t" . $value['content'] . "\t\t" . $value['vali'] ."\n";

			$text .= $base_url . '/messages.php?id=' . $value['id'] . " \n\n";
		}

		$this->mail->queue(['to' => 'admin', 'subject' => $subject, 'text' => $text, 'schema' => $schema]);
	}
}
