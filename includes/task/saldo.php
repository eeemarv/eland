<?php

namespace eland\task;

use Doctrine\DBAL\Connection as db;
use eland\xdb;
use Monolog\Logger;
use eland\groups;
use eland\multi_mail;

class saldo
{
	protected $db;
	protected $xdb;
	protected $monolog;
	protected $groups;
	protected $s3_img_url;
	protected $protocol;

	public function __construct(db $db, xdb $xdb, Logger $monolog,
		groups $groups, string $s3_img_url, string $protocol)
	{
		$this->db = $db;
		$this->xdb = $xdb;
		$this->monolog = $monolog;
		$this->groups = $groups;
		$this->s3_img_url = $s3_img_url;
		$this->protocol = $protocol;
	}

	function run($schema)
	{
		// vars

		$host = $this->groups->get_host($schema);

		if (!$host)
		{
			return;
		}

		$base_url = $this->protocol . $host;

		$r = "\r\n";
		$currency = readconfigfromdb('currency', $schema);
		$support = readconfigfromdb('support', $schema);
		$treshold_time = gmdate('Y-m-d H:i:s', time() - readconfigfromdb('saldofreqdays', $schema) * 86400); 	
		$msg_url = $base_url . '/messages.php?id=';
		$msgs_url = $base_url . '/messages.php';
		$news_url = $base_url . '/news.php?id=';
		$user_url = $base_url . '/users.php?id=';
		$login_url = $base_url . '/login.php?login=';
		$new_message_url = $base_url . '/messages.php?add=1';
		$new_transaction_url = $base_url . '/transactions.php?add=1';
		$account_edit_url = $base_url . '/users.php?edit=';

		$vars = [
			'group'		=> [
				'name'				=> readconfigfromdb('systemname'),
				'tag'				=> readconfigfromdb('systemtag'),
				'support'			=> readconfigfromdb('support'),
				'saldofreqdays'		=> readconfigfromdb('saldofreqdays'),
			],
			'messages_url'			=> $base_url . '/messages.php',
			'new_message_url'		=> $base_url . '/messages.php?add=1',
			'news_url'				=> $base_url . '/news.php',
			'transactions_url'		=> $base_url . '/transactions.php',
			'new_transaction_url'	=> $base_url . '/transactions.php?add=1',
		];

	// fetch active users

		$users = [];

		$rs = $this->db->prepare('SELECT u.id,
				u.name, u.saldo, u.status, u.minlimit, u.maxlimit,
				u.letscode, u.postcode, u.cron_saldo
			FROM ' . $schema . '.users u
			WHERE u.status in (1, 2)');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$users[$row['id']] = $row;

/*
			$this->mail->queue([
				'schema'	=> $schema,
				'to'		=> $user_id,
				'template'	=> 'periodic_overview',
				'vars'		=> array_merge($vars, [
					'user'	=> $row,
				],
			]);
*/
		}

	// fetch mail addresses & cron_saldo

		$mailaddr = $mailaddr_public = $saldo_mail = [];

		$st = $this->db->prepare('select u.id, c.value, c.flag_public
			from ' . $schema . '.users u, ' . $schema . '.contact c, ' . $schema . '.type_contact tc
			where u.status in (1, 2)
				and u.id = c.id_user
				and c.id_type_contact = tc.id
				and tc.abbrev = \'mail\'');

		$st->execute();

		while ($row = $st->fetch())
		{
			$user_id = $row['id'];
			$mail = $row['value'];
			$mailaddr[$user_id][] = $mail;
			$mailaddr_public[$user_id][] = $row['flag_public'];

			if (!$users[$user_id] || !$users[$user_id]['cron_saldo'])
			{
				continue;
			}

			$saldo_mail[$user_id] = true;


		}

	// start template

		$mm = new multi_mail();

		$t = '** Dit is een automatische mail. Niet beantwoorden a.u.b. **';
		$mm->add_text($t . $r . $r)
			->add_html('<p>' . $t . '</p>');

	// messages

		$t ='Recent LETS vraag en aanbod';
		$u ='---------------------------';
		$mm->add_text($t . $r . $u . $r)
			->add_html('<h2>' . $t . '</h2>');

		$t = 'Deze lijst bevat LETS vraag en aanbod dat de afgelopen ' . readconfigfromdb('saldofreqdays', $schema);
		$t .= ' dagen online is geplaatst. ';

		$mm->add_html('<p>')
			->add_text_and_html($t, 'msgs:any');

		$t = 'Er werd geen nieuw vraag of aanbod online geplaatst afgelopen ' .
			readconfigfromdb('saldofreqdays', $schema) . ' dagen. ';

		$mm->add_text_and_html($t, 'msgs:none')
			->add_text($r . $r . 'Geef zelf je eigen vraag of aanbod in: ' . $new_message_url . $r . $r)
			->add_html('Klik <a href="' . $new_message_url . '">hier</a> ')
			->add_html('om je eigen vraag of aanbod in te geven.</p><br><ul>');

		// fetch images
		
		$image_ary = [];

		$rs = $this->db->prepare('select m.id, p."PictureFile"
			from ' . $schema . '.msgpictures p, ' . $schema . '.messages m
			where p.msgid = m.id
				and m.cdate >= ?', [$treshold_time]);

		$rs->bindValue(1, $treshold_time);
		$rs->execute();

		while ($row = $rs->fetch())
		{
			$image_ary[$row['id']][] = $row['PictureFile'];
		}

		// fetch addresses

		$addr = $addr_public = [];

		$rs = $this->db->prepare('select u.id, c.value, flag_public
			from ' . $schema . '.users u, ' . $schema . '.contact c, ' . $schema . '.type_contact tc
			where u.status in (1, 2)
				and u.id = c.id_user
				and c.id_type_contact = tc.id
				and tc.abbrev = \'adr\'');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$addr[$row['id']] = $row['value'];
			$addr_public[$row['id']] = $row['flag_public'];
		}

		// fetch messages

		$rs = $this->db->prepare('SELECT m.id, m.content, m."Description", m.msg_type, m.id_user,
			u.name, u.letscode
			FROM ' . $schema . '.messages m, ' . $schema . '.users u
			WHERE m.id_user = u.id
				AND u.status IN (1, 2)
				AND m.cdate >= ?
			ORDER BY m.cdate DESC');

		$rs->bindValue(1, $treshold_time);
		$rs->execute();

		while ($msg = $rs->fetch())
		{
			$va = ($msg['msg_type']) ? 'Aanbod' : 'Vraag';

			if (isset($image_ary[$msg['id']]))
			{
				$image_count =  (count($image_ary[$msg['id']]) > 1) ? count($image_ary[$msg['id']]) . ' afbeeldingen' : '1 afbeelding';
				$html_img = '<a href="' . $msg_url . $msg['id'] . '"><img src="' . $this->s3_img_url . $image_ary[$msg['id']][0];
				$html_img .= '" height="200" alt="afbeelding"></a><br>';
			}
			else
			{
				$image_count = 'Geen afbeeldingen';
				$html_img = '';
			}

			$maillinks = '';

			$vacont = $va . ': ' . $msg['content'];

			foreach ($mailaddr[$msg['id_user']] as $k => $mailaddr_p)
			{
				if ($mailaddr_public[$msg['id_user']][$k] < 1)
				{
					continue;
				}

				$maillinks .= ' | <a href="mailto:' . $mailaddr_p . '?subject=' . urlencode($vacont . ' (reaktie)') . '">email</a>';	
			}

			$description = ($msg['Description']) ? $msg['Description'] . '<br>' : '';

			$postcode = $users[$msg['id_user']]['postcode'];
			$postcode = ($postcode) ? ' | postcode: ' . $postcode : '';

			$text = $vacont . ' (' . $image_count . ')' . $r . $msg_url . $msg['id'] . $r;
			$text .= 'Ingegeven door: ' . $msg['letscode'] . ' ' . $msg['name'] . ' ';
			$text .= $user_url . $msg['id_user'] . $postcode . $r . $r;

			$html = '<li><b><a href="' . $msg_url . $msg['id'] . '">' . $vacont . '</a></b> (';
			$html .= $image_count . ')<br>' . $html_img . $description . 'Ingegeven door <a href="';
			$html .= $user_url . $msg['id_user'] . '">';
			$html .= $msg['letscode'] . ' ' . $msg['name'] . '</a>' . $postcode . $maillinks;

			$mm->add_text($text, 'msgs:en')
				->add_html($html);

			if ($addr_public[$msg['id_user']] > 0)
			{
				$mm->add_html(' | <a href="https://www.google.be/maps/dir/', 'googleaddr');
				$mm->add_html_var('googleaddr', 'googleaddr');
				$mm->add_html('/' . str_replace(' ', '+', $addr[$msg['id_user']]) . '">route</a>', 'googleaddr');
			}

			$mm->add_html('</li><br>');
		}

		$mm->add_html('</ul>')
			->add_html('<a href="' . $msgs_url . '">Bekijk alle vraag en aanbod online</a> .')
			->add_text('Bekijk alle vraag en aanbod online: ' . $msgs_url . $r . $r);

	// news

		$mm->add_text('Nieuws' . $r)
			->add_text('------' . $r)
			->add_text('Bekijk online: ' . $base_url . '/news.php' . $r . $r)
			->add_html('<h2>Nieuws</h2>')
			->add_html('<ul>', 'news:any')
			->add_text('Momenteel zijn er geen nieuwsberichten.' . $r . $r, 'news:none')
			->add_html('<p>Momenteel zijn er geen nieuwsberichten.</p>', 'news:none');

		$news_access_ary = [];

		$rows = $this->xdb->get_many(['agg_schema' => $schema, 'agg_type' => 'news_access']);

		foreach ($rows as $row)
		{
			$access = $row['data']['access'];
			$news_access_ary[$row['eland_id']] = $access;
		}

		$rs = $this->db->prepare('select n.*, u.name, u.letscode
			from ' . $schema . '.news n, ' . $schema . '.users u
			where n.approved = \'t\'
				and n.published = \'t\'
				and n.id_user = u.id
			order by n.cdate desc');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			if (isset($news_access_ary[$row['id']]))
			{
				$news_access = $news_access_ary[$row['id']];
			}
			else
			{
				$this->xdb->set('news_access', $news_id, ['access' => 'interlets'], $schema);
				$news_access = 'interlets';
			}

			if (!in_array($news_access, ['users', 'interlets']))
			{
				continue;
			} 

			$location_text = ($row['location']) ? 'Locatie: ' . $row['location'] . $r : '';
			$location_html = ($row['location']) ? 'Locatie: <b>' . $row['location'] . '</b><br>' : '';

			$itemdate = strstr($row['itemdate'], ' ', true);

			$mm->add_text('*** ' . $row['headline'] . ' ***' . $r, 'news:en')
				->add_text($location_text . 'Datum: ' . $itemdate . $r)
				->add_text('Bericht: ' . $row['newsitem'] . $r)
				->add_text('Ingegeven door: ' . $row['letscode'] . ' ' . $row['name'] . $r . $r . $r)
				->add_html('<li><a href="' . $news_url . $row['id'] . '">' . $row['headline'] . '</a><br>')
				->add_html($location_html . 'Datum: <b>' . $itemdate . '</b><br>')
				->add_html('Bericht: ' . $row['newsitem'] . '<br>')
				->add_html('Ingegeven door: <a href="' . $user_url . $row['id_user'] . '">')
				->add_html($row['letscode'] . ' ' . $row['name'] . '</a></li><br>');
		}

		$mm->add_html('</ul>', 'news:any');

	// new users

		$mm->add_text('Nieuwe leden' . $r)
			->add_text('------------' . $r . $r)
			->add_text('Momenteel zijn er geen nieuwe leden.' . $r . $r, 'new_users:none')
			->add_html('<h2>Nieuwe leden</h2>')
			->add_html('<ul>', 'new_users:any')
			->add_html('<p>Momenteel zijn er geen nieuwe leden.</p>', 'new_users:none');

		$rs = $this->db->prepare('select u.id, u.name, u.letscode, u.postcode
			from ' . $schema . '.users u
			where u.status = 1
				and u.adate > ?');

		$rs->bindValue(1, gmdate('Y-m-d H:i:s', time() - readconfigfromdb('newuserdays', $schema) * 86400));
		$rs->execute();

		while ($row = $rs->fetch())
		{
			$postcode = ($row['postcode']) ? ' | postcode: ' . $row['postcode'] : '';

			$mm->add_text($row['letscode'] . ' ' . $row['name'] . ' ', 'new_users:en')
				->add_text($user_url . $row['id'] . $postcode . $r)
				->add_html('<li><a href="' . $user_url . $row['id'] . '">')
				->add_html($row['letscode'] . ' ' . $row['name'] . '</a>' . $postcode . '</li>');
		}

		$mm->add_text($r)
			->add_html('</ul>', 'new_users:any');

	// leaving users

		$mm->add_text('Uitstappende leden' . $r)
			->add_text('------------------' . $r . $r)
			->add_text('Momenteel zijn er uitstappende leden.' . $r . $r, 'leaving_users:none')
			->add_html('<h2>Uitstappende leden</h2>')
			->add_html('<ul>', 'leaving_users:any')
			->add_html('<p>Momenteel zijn er geen uitstappende leden.</p>', 'leaving_users:none');

		$rs = $this->db->prepare('select u.id, u.name, u.letscode, u.postcode
			from ' . $schema . '.users u
			where u.status = 2');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$postcode = ($row['postcode']) ? ' | postcode: ' . $row['postcode'] : '';

			$mm->add_text($row['letscode'] . ' ' . $row['name'] . ' ', 'leaving_users:en')
				->add_text($user_url . $row['id'] . $postcode . $r)
				->add_html('<li><a href="' . $user_url . $row['id'] . '">')
				->add_html($row['letscode'] . ' ' . $row['name'] . '</a>' . $postcode . '</li>');
		}

		$mm->add_text($r)
			->add_html('</ul>', 'leaving_users:any');

	// recent transactions

		$mm->add_text('Recente transacties' . $r)
			->add_text('-------------------' . $r . $r)
			->add_html('<h2>Recente transacties</h2><p>');
		$t = 'Deze lijst toont de transacties van de laatste ' . readconfigfromdb('saldofreqdays', $schema) . ' dagen.';
		$mm->add_text_and_html($t, 'trans:any');
		$t = 'Er werden geen nieuwe transacties gedaan afgelopen ' . readconfigfromdb('saldofreqdays', $schema) . ' dagen. ';
		$mm->add_text_and_html($t, 'trans:none')
			->add_text($r . $r . 'Nieuwe transactie ingeven: ' . $new_transaction_url . $r . $r)
			->add_html('</p><p>Klik <a href="' . $new_transaction_url . '">hier</a> ')
			->add_html(' om een nieuwe transactie in te geven.</p><ul>');

		$rs = $this->db->prepare('select t.id_from, t.id_to, t.real_from, t.real_to,
				t.amount, t.cdate, t.description,
				uf.name as name_from, uf.letscode as letscode_from,
				ut.name as name_to, ut.letscode as letscode_to
			from ' . $schema . '.transactions t, ' . $schema . '.users uf, ' . $schema . '.users ut
			where t.id_from = uf.id
				and t.id_to = ut.id
				and t.cdate > ?');

		$rs->bindValue(1, $treshold_time);
		$rs->execute();

		while ($row = $rs->fetch())
		{
			$tr_from_text = ($row['real_from']) ? $row['name_from'] . ': ' . $row['real_from'] : $row['letscode_from'] . ' ' . $row['name_from'];
			$tr_to_text = ($row['real_to']) ? $row['name_to'] . ': ' . $row['real_to'] : $row['letscode_to'] . ' ' .$row['name_to'];

			$tr_from_html = ($row['real_from']) ? $tr_from_text : '<a href="' . $user_url . $row['id_from'] . '">' . $tr_from_text . '</a>';
			$tr_to_html = ($row['real_to']) ? $tr_to_text : '<a href="' . $user_url . $row['id_to'] . '">' . $tr_to_text . '</a>';

			$mm->add_text('* ' . $row['amount'] . ' ' . $currency . ' van ' . $tr_from_text, 'trans:en')
				->add_text(' naar ' . $tr_to_text . $r . "\t" . $row['description'] . $r . $r)
				->add_html('<li>' . $row['amount'] . ' ' . $currency . ' van ' . $tr_from_html)
				->add_html(' naar ' . $tr_to_html . '<br>' . $row['description'] . '</li><br>');
		}

		$mm->add_text($r)
			->add_html('</ul>');

	//your account

		$mm->add_text('Je account' . $r)
			->add_text('----------' . $r . $r)
			->add_html('<h2>Je account</h2>')

			->add_text('Je letscode: ')
			->add_text_var('letscode')
			->add_text($r . 'Je gebruikersnaam: ')
			->add_text_var('name')
			->add_text($r)

			->add_html('<p>Je letscode: <b>')
			->add_html_var('letscode')
			->add_html('</b></p><p>Je gebruikersnaam: <b>')
			->add_html_var('name')
			->add_html('</b></p>')

			->add_text('Je saldo bedraagt momenteel ')
			->add_text_var('saldo')
			->add_text(' ' . $currency . $r)

			->add_html('<p>Je saldo bedraagt momenteel <b>')
			->add_html_var('saldo')
			->add_html('</b> ' . $currency . '</p>')

			->add_text('Minimum limiet: ')
			->add_text_var('minlimit')
			->add_text(' ' . $currency . ', Maximum limiet: ')
			->add_text_var('maxlimit')
			->add_text(' ' . $currency . $r)

			->add_html('<p>Minimum limiet: <b>')
			->add_html_var('minlimit')
			->add_html('</b> ' . $currency . ', Maximum limiet: <b>')
			->add_html_var('maxlimit')
			->add_html('</b> ' . $currency . '</p>')

			->add_text('Status: ')
			->add_text_var('status')
			->add_text($r)

			->add_html('<p>Status: <b>')
			->add_html_var('status')
			->add_html('</b></p>')

			->add_text('Login: ' . $login_url)
			->add_text_var('letscode')
			->add_text($r . $r)

			->add_html('<p>Login: <b><a href="' . $login_url)
			->add_html_var('letscode')
			->add_html('">')
			->add_html_var('letscode')
			->add_html('</a></b></p>');

	// support

		$mm->add_text('Support' . $r)
			->add_text('-------' . $r . $r)
			->add_text('Als je een probleem ervaart, kan je mailen naar ' . $support . $r . $r)

			->add_html('<h2>Support</h2>')
			->add_html('<p>Neem <a href="mailto:' . $support . '">contact</a> ')
			->add_html('op met ons als je een probleem ervaart.</p>')

			->add_text('Je ontvangt deze mail omdat de optie \'Periodieke mail met recent vraag en aanbod\' ')
			->add_text('aangevinkt staat in je instellingen. ' . $r)
			->add_text('Wil je deze mail niet meer ontvangen, vink deze optie dan uit: ')
			->add_text($account_edit_url)
			->add_text_var('id')

			->add_html('<p>Je ontvangt deze mail omdat de optie \'Periodieke mail met recent vraag en aanbod\' ')
			->add_html('aangevinkt staat in je instellingen. ')
			->add_html('Klik <a href="' . $account_edit_url)
			->add_html_var('id')
			->add_html('">hier</a> om aan te passen</p>'); 

	//queue mail for sending

		$subject = 'Recent vraag en aanbod';
		$log_to = [];

		foreach ($saldo_mail as $user_id => $d)
		{
			$mm->set_vars($users[$user_id])
				->set_var('status', ($users[$user_id]['status'] == 2) ? 'uitstapper' : 'actief');

			if (isset($addr[$user_id]))
			{
				$mm->set_var('googleaddr', str_replace(' ', '+', $addr[$user_id]));
			}

			$mm->mail_q(['to' => $user_id, 'subject' => $subject, 'schema' => $schema]);
			$log_to[] = $users[$user_id]['letscode'] . ' ' . $users[$user_id]['name'] . ' (' . $user_id . ')';
		}

		if (count($log_to))
		{
			$this->monolog->info('Saldomail queued, subject: ' . $subject . ', to: ' . implode(', ', $log_to), ['schema' => $schema]);
		}
		else
		{
			$this->monolog->info('mail: no saldomail queued', ['schema' => $schema]);
		}

		return true;


	}
}
