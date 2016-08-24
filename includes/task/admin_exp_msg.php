<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use eland\task\mail;

class admin_exp_msg
{
	protected $db;
	protected $mail;
	protected $base_url;

	public function __construct(db $db, mail $mail, string $base_url)
	{
		$this->db = $db;
		$this->mail = $mail;
		$this->base_url = $base_url;
	}

	function run()
	{
		$now = gmdate('Y-m-d H:i:s');

		$query = 'SELECT m.id_user, m.content, m.id, to_char(m.validity, \'YYYY-MM-DD\') as vali
			FROM messages m, users u
			WHERE u.status <> 0
				AND m.id_user = u.id
				AND validity <= ?';
		$messages = $this->db->fetchAll($query, [$now]);

		$subject = 'Rapport vervallen Vraag en aanbod';

		$text = "-- Dit is een automatische mail, niet beantwoorden aub --\n\n";
		$text .= "Gebruiker\t\tVervallen vraag of aanbod\t\tVervallen\n\n";
		
		foreach($messages as $key => $value)
		{
			$text .= link_user($value['id_user'], false, false) . "\t\t" . $value['content'] . "\t\t" . $value['vali'] ."\n";
			$text .= $this->base_url . '/messages.php?id=' . $value['id'] . " \n\n";
		}

		$this->mail->queue(['to' => 'admin', 'subject' => $subject, 'text' => $text]);

	}
}
