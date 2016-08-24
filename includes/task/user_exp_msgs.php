<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use eland\task\mail;

class user_exp_msgs
{
	protected $db;
	protected $mail;

	public function __construct(db $db, mail $mail, string $base_url)
	{
		$this->db = $db;
		$this->mail = $mail;
		$this->base_url = $base_url;
	}

	function run()
	{
		$now = gmdate('Y-m-d H:i:s');

		$msgcleanupdays = readconfigfromdb('msgexpcleanupdays');

		$warn_messages  = $this->db->fetchAll('SELECT m.*
			FROM messages m
				WHERE m.exp_user_warn = \'f\'
					AND m.validity < ?', [$now]);

		foreach ($warn_messages AS $key => $value)
		{

			echo 'Found new expired message ' . $value['id'];

			$user = readuser($value['id_user']);

			$extend_url = $this->base_url . '/messages.php?id=' . $value['id'] . '&extend=';

			$va = ($value['msg_type']) ? 'aanbod' : 'vraag';

			$text = "-- Dit is een automatische mail, niet beantwoorden aub --\r\n\r\n";
			$text .= "Beste " . $user['name'] . "\n\nJe " . $va . ' ' . $value['content'] . ' ';
			$text .= 'is vervallen en zal over ' . $msgcleanupdays . ' dagen verwijderd worden. ';
			$text .= 'Om dit te voorkomen kan je verlengen met behulp van één van de onderstaande links (Als ';
			$text .= 'je niet ingelogd bent, zal je eerst gevraagd worden in te loggen). ';
			$text .= "\n\n Verlengen met \n\n";
			$text .= "één maand: " . $extend_url . "30 \n";
			$text .= "twee maanden: " . $extend_url . "60 \n";
			$text .= "zes maanden: " . $extend_url . "180 \n";
			$text .= "één jaar: " . $extend_url . "365 \n";
			$text .= "twee jaar: " . $extend_url . "730 \n";
			$text .= "vijf jaar: " . $extend_url . "1825 \n\n";
			$text .= "Nieuw vraag of aanbod ingeven: " . $this->base_url . "/messages.php?add=1 \n\n";
			$text .= "Als je nog vragen of problemen hebt, kan je mailen naar ";
			$text .= readconfigfromdb('support');

			$subject = 'Je ' . $va . ' is vervallen.';

			if (empty($from))
			{
				echo "Mail from address is not set in configuration\n";
				return;
			}

			$this->mail->queue(['to' => $value['id_user'], 'subject' => $subject, 'text' => $text]);
		}

		$this->db->executeUpdate('update messages set exp_user_warn = \'t\' WHERE validity < ?', [$now]);

	}
}
