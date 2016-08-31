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

		if ($schema == 'x')
		{
			$warn_messages  = $this->db->fetchAll('SELECT m.*
				FROM ' . $schema . '.messages m
					WHERE m.exp_user_warn = \'f\' limit 1');
		}
		else
		{
			echo 'bye';
			return;
		}

		$group_vars = $this->groups->get_template_vars($schema);

//		$msgcleanupdays = readconfigfromdb('msgexpcleanupdays', $schema);
/*
		$warn_messages  = $this->db->fetchAll('SELECT m.*
			FROM ' . $schema . '.messages m
				WHERE m.exp_user_warn = \'f\'
					AND m.validity < ?', [$now]);
*/

		foreach ($warn_messages as $key => $msg)
		{
			$msg['type'] = ($msg['msg_type']) ? 'offer' : 'want';

			$url_extend = [];

			foreach ([7, 30, 60, 180, 365, 730, 1825] as $ext_days)
			{
				$url_extend[$ext_days] = $base_url . '/messages.php?id=' . $msg['id'] . '&extend=' . $ext_days;
			}

/*
			$va = ($msg['msg_type']) ? 'aanbod' : 'vraag';

			$extend_url = $base_url . '/messages.php?id=' . $msg['id'] . '&extend=';

			$text = "-- Dit is een automatische mail, niet beantwoorden aub --\r\n\r\n";
			$text .= "Beste " . $user['name'] . "\n\nJe " . $va . ' ' . $msg['content'] . ' ';
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
			$text .= "Nieuw vraag of aanbod ingeven: " . $base_url . "/messages.php?add=1 \n\n";
			$text .= "Als je nog vragen of problemen hebt, kan je mailen naar ";
			$text .= readconfigfromdb('support', $schema);

			$subject = 'Je ' . $va . ' is vervallen.';
*/
			$vars = [
				'msg' 			=> $msg,
				'user'			=> readuser($msg['id_user'], $schema),
				'url_extend' 	=> $url_extend,
				'url_msg_add'	=> $base_url . '/messages.php?add=1',
				'group'			=> $group_vars,
			];

			$this->mail->queue(['to' => $msg['id_user'],
//				'subject' => $subject,
//				'text' => $text,
				'schema' => $schema,
				'template' => 'user_exp_msgs',
				'vars' => $vars]);
		}

//		$this->db->executeUpdate('update ' . $schema . '.messages set exp_user_warn = \'t\' WHERE validity < ?', [$now]);

	}
}
