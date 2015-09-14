<?php

function saldo()
{
	global $db, $base_url;

	if (!readconfigfromdb('mailenabled'))
	{
		echo 'Mail functions are not enabled. ' . "\n";
		return true;
	}
	
	$from = readconfigfromdb("from_address_transactions");
	if (empty($from))
	{
		echo 'Mail from_address_transactions is not set in configuration' . "\n";
		return true;
	}

	$mailaddr = $to = $merge_vars = $msgs = $news = $new_users = $leaving_users = $transactions = $to_mail = array();

	$treshold_time = gmdate('Y-m-d H:i:s', time() - readconfigfromdb('saldofreqdays') * 86400); 

	$addr = $db->fetchAll('select u.id, c.value
		from users u, contact c, type_contact tc
		where u.status in (1, 2)
			and u.id = c.id_user
			and c.id_type_contact = tc.id
			and tc.abbrev = \'adr\'');

	assoc($addr);
	
	$users = $db->fetchAll('SELECT u.id,
			u.name, u.saldo, u.status, u.minlimit, u.maxlimit,
			u.fullname, u.letscode, u.login
		FROM users u
		WHERE u.status in (1, 2)
		AND u.cron_saldo = \'t\'');

	assoc($users);

	$st = $db->prepare('select u.id, c.value
		from users u, contact c, type_contact tc
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

		if (!$users[$user_id])
		{
			continue;
		}

		$users[$user_id]['id'] = $user_id;

		$user = $users[$user_id];

		$to[] = array(
			'email'	=> $mail,
			'name'	=> $user['name'],
		);

		$to_mail[] = $mail;

		$merge_vars[] = array(
			'rcpt'	=> $mail,
			'vars'	=> array(
				array(
					'name'		=> 'NAME',
					'content'	=> $user['name'],
				),
				array(
					'name'		=> 'BALANCE',
					'content'	=> $user['saldo'],
				),
				array(
					'name'		=> 'LETSCODE',
					'content'	=> $user['letscode'],
				),
				array(
					'name'		=> 'FULLNAME',
					'content'	=> $user['fullname'],
				),
				array(
					'name'		=> 'ID',
					'content'	=> $user['id'],
				),
				array(
					'name'		=> 'STATUS',
					'content'	=> ($user['status'] == 2) ? 'uitstapper' : 'actief',
				),
				array(
					'name'		=> 'MINLIMIT',
					'content'	=> $user['minlimit'],
				),
				array(
					'name'		=> 'MAXLIMIT',
					'content'	=> $user['maxlimit'],
				),
				array(
					'name'		=> 'LOGIN',
					'content'	=> $user['login'],
				),
				array(
					'name'		=> 'GOOGLEADDR',
					'content'	=> str_replace(' ', '+', $addr[$user_id]),
				),
			),
		);
	}

	$r = "\r\n";
	$currency = readconfigfromdb('currency');
	$support = readconfigfromdb('support');
	$msg_url = $base_url . '/messages/view.php?id=';
	$news_url = $base_url . '/news/view.php?id=';
	$user_url = $base_url . '/memberlist_view.php?&id=';
	$login_url = $base_url . '/login.php?login=*|LOGIN|*';
	$new_message_url = $base_url . '/messages/edit.php?mode=new';
	$new_transaction_url = $base_url . '/transactions/add.php';
	$mydetails_url = $base_url . '/userdetails/mydetails_edit.php';

	$image_count_ary = $db->fetchAll('select m.id, count(p.id)
		from msgpictures p, messages m
		where p.msgid = m.id
			and m.cdate >= ?
		group by m.id', array($treshold_time));

	assoc($image_count_ary);

	$st = $db->prepare('SELECT m.id, m.content, m."Description", m.msg_type, m.id_user,
		u.fullname, u.letscode
		FROM messages m, users u
		WHERE m.id_user = u.id
			AND u.status IN (1, 2)
			AND m.cdate >= ?
		ORDER BY m.cdate DESC');

	$st->bindValue(1, $treshold_time);
	$st->execute();

	while ($msg = $rs->fetch())
	{
		$va = ($msg['msg_type']) ? 'Aanbod' : 'Vraag';

		$image_count = ($image_count_ary[$msg['id']]) ? (($image_count_ary[$msg['id']] > 1) ? $image_count_ary[$msg['id']] . ' afbeeldingen' : '1 afbeelding') : 'Geen afbeeldingen';

		$mailto = $mailaddr[$msg['id_user']];

		$google_maps = 'https://www.google.be/maps/dir/*|GOOGLEADDR|*/' . str_replace(' ', '+', $addr[$msg['id_user']]);

		$description = ($msg['Description']) ? $msg['Description'] . '<br>' : '';

		$msgs[] = array(
			'text'	=> $va . ': ' . $msg['content'] . ' (' . $image_count . ')' . $r . $msg_url . $msg['id'] . $r .
				'Ingegeven door: ' . $msg['fullname'] . ' (' . $msg['letscode'] . ') ' . $user_url . $msg['id_user'] . $r . $r,
			'html'	=> '<li><b><a href="' . $msg_url . $msg['id'] . '">' . $va . ': ' . $msg['content'] . '</a></b> (' .
				$image_count . ')<br>' . $description . 'Ingegeven door <a href="' . $user_url . $msg['id_user'] . '">' .
				$msg['fullname'] . ' (' . $msg['letscode'] . ')</a> | <a href="mailto:' . $mailto .
				'">email</a> | <a href="' . $google_maps . '">route</a> ' .
				'</li><br>',
		);
	}

	$st = $db->prepare('select n.*, u.fullname, u.letscode
		from news n, users u
		where n.approved = \'t\'
			and n.published = \'t\'
			and n.id_user = u.id
		order by n.cdate desc');

	$st->execute();

	while ($row = $st->fetch())
	{
		$location_text = ($row['location']) ? 'Locatie: ' . $row['location'] . $r : '';
		$location_html = ($row['location']) ? 'Locatie: <b>' . $row['location'] . '</b><br>' : '';

		$itemdate = strstr($row['itemdate'], ' ', true);

		$news[] = array(
			'text'	=> '*** ' . $row['headline'] . ' ***' . $r  .
				$location_text .
				'Datum: ' . $itemdate . $r .
				'Ingegeven door: ' . $row['fullname'] . ' (' . $row['letscode'] . ')' . $r .
				$row['newsitem'] . $r . $r,
				
 			'html'	=> '<li><a href="' . $news_url . $row['id'] . '">' . $row['headline'] . '</a><br>' .
				$location_html .
				'Datum: <b>' . $itemdate . '</b>' .
				'Ingegeven door: <a href="' . $user_url . $row['id_user'] . '">' .
				$row['fullname'] . ' (' . $row['letscode'] . ')</a><br>' .
				$row['newsitem'] . '</li><br>',
		);
	}

	$st = $db->prepare('select u.id, u.fullname, u.letscode
		from users u
		where u.status = 1
			and u.adate > ?');

	$st->bindValue(1, gmdate('Y-m-d H:i:s', time() - readconfigfromdb('newuserdays') * 86400));
	$st->execute();

	while ($row = $st->fetch())
	{
		$new_users[] = array(
			'text'	=> $row['fullname'] . ' (' . $row['letscode'] . ') ' . $user_url . $row['id'] . $r,
			'html'	=> '<li><a href="' . $user_url . $row['id'] . '">' . $row['fullname'] . ' (' . $row['letscode'] . ')</a></li>',
		);
	}

	$st = $db->prepare('select u.id, u.fullname, u.letscode
		from users u
		where u.status = 2');

	$st->execute();

	while ($row = $st->fetch())
	{
		$leaving_users[] = array(
			'text'	=> $row['fullname'] . ' (' . $row['letscode'] . ') ' . $user_url . $row['id'] . $r,
			'html'	=> '<li><a href="' . $user_url . $row['id'] . '">' . $row['fullname'] . ' (' . $row['letscode'] . ')</a></li>',
		);
	}

	$st = $db->prepare('select t.id_from, t.id_to, t.real_from, t.real_to,
			t.amount, t.cdate, t.description,
			uf.fullname as fullname_from, uf.letscode as letscode_from,
			ut.fullname as fullname_to, ut.letscode as letscode_to
		from transactions t, users uf, users ut
		where t.id_from = uf.id
			and t.id_to = ut.id
			and t.cdate > ?');

	$st->bindValue(1, $treshold_time);
	$st->execute();

	while ($row = $st->fetch())
	{
		$tr_from_text = ($row['real_from']) ? $row['fullname_from'] . ': ' . $row['real_from'] : $row['fullname_from'] . ' (' . $row['letscode_from'] . ')';
		$tr_to_text = ($row['real_to']) ? $row['fullname_to'] . ': ' . $row['real_to'] : $row['fullname_to'] . ' (' . $row['letscode_to'] . ')';

		$tr_from_html = ($row['real_from']) ? $tr_from_text : '<a href="' . $user_url . $row['id_from'] . '">' . $tr_from_text . '</a>';
		$tr_to_html = ($row['real_to']) ? $tr_to_text : '<a href="' . $user_url . $row['id_to'] . '">' . $tr_to_text . '</a>';

		$transactions[] = array(
			'text'	=> '* ' . $row['amount'] . ' ' . $currency . ' van ' . $tr_from_text . ' naar ' . $tr_to_text . $r .
				"\t" . $row['description'] . $r . $r,
			'html'	=> '<li>' . $row['amount'] . ' ' . $currency . ' van ' . $tr_from_html . ' naar ' . $tr_to_html . '<br>' .
				$row['description'] . '</li><br>',
		);
	}

	$t = 'Dit is een automatisch gegenereerde mail. Niet beantwoorden a.u.b.';
	$text = $t . $r . $r;
	$html = $t . '<br>';

	$text .= 'Beste *|FULLNAME|* (*|LETSCODE|*)' . $r . $r;
	$html .= '<p>Beste <b>*|FULLNAME|* (*|LETSCODE|*)</b>,</p>';

	$text .= 'Je huidig saldo bedraagt *|BALANCE|* ' . $currency . $r;
	$html .= '<p>Je huidig saldo bedraagt <b>*|BALANCE|* </b> ' . $currency . '</p>';

	$text .= 'Minimum limiet: *|MINLIMIT|* ' . $currency . ', Maximum limiet: *|MAXLIMIT|* ' . $currency . $r;
	$html .= '<p>Minimum limiet: <b>*|MINLIMIT|*</b> ' . $currency . ', Maximum limiet: <b>*|MAXLIMIT|*</b> ' . $currency . '</p>';

	$text .= 'Status: *|STATUS|*' . $r;
	$html .= '<p>Status: <b>*|STATUS|*</b></p>';

	$text .= 'Login: *|LOGIN|* ' . $login_url . $r . $r;
	$html .= '<p>Login: <b>*|LOGIN|*</b> ' . $login_url . '</p>';

	$t ='Recent LETS vraag en aanbod';
	$u ='---------------------------';
	$text .= $t . $r . $u . $r;
	$html .= '<h1>' . $t . '</h1>';

	if (count($msgs))
	{
		$t = 'Deze lijst bevat LETS vraag en aanbod dat de afgelopen ' . readconfigfromdb('saldofreqdays') .
			' dagen in eLAS is geplaatst. ';
	}
	else
	{
		$t = 'Er werd geen nieuw vraag of aanbod in eLAS geplaatst afgelopen ' .
			readconfigfromdb('saldofreqdays') . ' dagen. ';
	}

	$text .= $t . 'Geef zelf je eigen vraag of aanbod in: ' . $new_message_url . $r . $r;
	$html .= '<p>' . $t . 'Klik <a href="' . $new_message_url . '">hier</a> om je eigen vraag of aanbod in te geven.</p><br>';
	$html .= '<ul>';

	foreach ($msgs as $msg)
	{
		$text .= $msg['text'];
		$html .= $msg['html'];
	}

	$html .= '</ul>';

	$text .= 'Nieuws' . $r;
	$text .= '------' . $r;
	$text .= 'Bekijk online: ' . $base_url . '/news/overview.php' . $r . $r;
	$html .= '<h1>Nieuws</h1>';

	if (count($news))
	{
		$html .= '<ul>';

		foreach ($news as $item)
		{
			$text .= $item['text'];
			$html .= $item['html'];
		}
		$html .= '</ul>';
	}
	else
	{
		$t = 'Momenteel zijn er geen nieuwsberichten.';
		$text .=  $t . $r . $r;
		$html .= '<p>' . $t . '</p>';
	}

	$text .= 'Nieuwe leden' . $r;
	$text .= '------------' . $r . $r;
	$html .= '<h1>Nieuwe leden</h1>';
	
	if (count($new_users))
	{
		$html .= '<ul>';

		foreach ($new_users as $new_user)
		{
			$text .= $new_user['text'];
			$html .= $new_user['html'];
		}

		$text .= $r;
		$html .= '</ul>';
	}
	else
	{
		$t = 'Momenteel zijn er geen nieuwe leden.';
		$text .=  $t . $r . $r;
		$html .= '<p>' . $t . '</p>';
	}


	$text .= 'Uitstappers' . $r;
	$text .= '-----------' . $r . $r;
	$html .= '<h1>Uitstappers</h1>';

	if (count($leaving_users))
	{
		$html .= '<ul>';

		foreach ($leaving_users as $leaving_user)
		{
			$text .= $leaving_user['text'];
			$html .= $leaving_user['html'];
		}

		$text .= $r;
		$html .= '</ul>';
	}
	else
	{
		$t = 'Momenteel zijn er geen uitstappende leden.';
		$text .=  $t . $r . $r;
		$html .= '<p>' . $t . '</p>';
	}

	$text .= 'Recente transacties' . $r;
	$text .= '-------------------' . $r . $r;
	if (count($transactions))
	{
		$t= 'Deze lijst toont de transacties van de laatste ' . readconfigfromdb('saldofreqdays') . ' dagen.';
	}
	else
	{
		$t = 'Er werden geen nieuwe transacties in eLAS gedaan afgelopen ' .
		readconfigfromdb('saldofreqdays') . ' dagen. ';
	}
	$text .= $t . $r;
	$text .= 'Nieuwe transactie ingeven: ' . $new_transaction_url . $r . $r;
	$html .= '<h1>Recente transacties</h1>';
	$html .= '<p>' . $t . '</p>';
	$html .= '<p>Klik <a href="' . $new_transaction_url . '">hier</a> om een nieuwe transactie in te geven.</p>';
	$html .= '<ul>';

	foreach ($transactions as $transaction)
	{
		$text .= $transaction['text'];
		$html .= $transaction['html'];
	}

	$text .= $r;
	$html .= '</ul>';

	$t = 'Support';
	$u = '-------';

	$text .= $t . $r . $u . $r . 'Als je een probleem ervaart, kan je mailen naar ' . $support . $r . $r;
	$html .= '<h1>' . $t .'</h1><p>Neem <a href="mailto:' . $support .
		'">contact</a> op met ons als je een probleem ervaart.</p>';

	$t = 'Je ontvangt deze mail omdat de optie \'Saldo mail met recent vraag en aanbod\' aangevinkt staat ';
	$t .= 'in je instellingen. ';
	$text .= $t . 'Wil je deze mail niet meer ontvangen, vink deze optie dan uit: ' . $mydetails_url;
	$html .= '<p>' . $t . 'Klik <a href="' . $mydetails_url . '">hier</a> om aan te passen</p>';

	$subject = '[eLAS-'. readconfigfromdb('systemtag') .'] - Saldo, recent vraag en aanbod en nieuws.';

	$message = array(
		'subject'		=> $subject,
		'text'			=> $text,
		'html'			=> $html,
		'from_email'	=> $from,
		'to'			=> $to,
		'merge_vars'	=> $merge_vars,
	);

	try
	{
		$mandrill = new Mandrill();
		$mandrill->messages->send($message, true);
	}
	catch (Mandrill_Error $e)
	{
		log_event($s_id, 'mail', 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage());
		return;
	}

	$to = (is_array($to)) ? implode(', ', $to) : $to;

	log_event('', 'Mail', 'Saldomail sent, subject: ' . $subject . ', from: ' . $from . ', to: ' . implode(', ', $to_mail));

	return true;
}
