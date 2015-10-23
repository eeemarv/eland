<?php
/**
 * This file is part of eLAS http://elas.vsbnet.be
 *
 * Copyright(C) 2009 Guy Van Sanden <guy@vsbnet.be>
 *
 * eLAS is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

function generate_transid()
{
	global $base_url, $s_id;
	return substr(sha1($s_id .microtime()), 0, 12) . '_' . $s_id . '@' . $base_url;
}

function sign_transaction($transaction, $sharedsecret)
{
	$signamount = (float) $transaction['amount'];
	$signamount = $signamount * 100;
	$signamount = round($signamount);
	$tosign = $sharedsecret . $transaction['transid'] . strtolower($transaction['letscode_to']) . $signamount;
	$signature = sha1($tosign);
	log_event('','debug','Signing ' . $tosign . ' : ' . $signature);
	return $signature;
}

function insert_transaction($transaction)
{
    global $db, $s_id;

	$transaction['creator'] = (empty($s_id)) ? 0 : $s_id;
    $transaction['cdate'] = date('Y-m-d H:i:s');

	$db->beginTransaction();
	try
	{
		$db->insert('transactions', $transaction);
		$id = $db->lastInsertId('transactions_id_seq');
		$db->executeUpdate('update users set saldo = saldo + ? where id = ?', array($transaction['amount'], $transaction['id_to']));
		$db->executeUpdate('update users set saldo = saldo - ? where id = ?', array($transaction['amount'], $transaction['id_from']));
		$db->commit();

	}
	catch(Exception $e)
	{
		$db->rollback();
		throw $e;
		return false;
	}

	$to_user = readuser($transaction['id_to'], true);
	$from_user = readuser($transaction['id_from'], true);

	autominlimit_queue($transaction['id_from'], $transaction['id_to'], $transaction['amount']);

	log_event($s_id, 'Trans', 'Transaction ' . $transaction['transid'] . ' saved: ' .
		$transaction['amount'] . ' ' . readconfigfromdb('currency') . ' from user ' .
		link_user($transaction['id_from'], null, false, true) . ' to user ' .
		link_user($transaction['id_to'], null, false, true));

	return $id;
}

function mail_interlets_transaction($transaction)
{
	global $s_id;

	$r = "\r\n";
	$t = "\t";

	$from = readconfigfromdb('from_address_transactions');

	$userfrom = readuser($transaction['id_from']);

	$to = get_mailaddresses($transaction['id_to']);

	$systemname = readconfigfromdb('systemname');
	$systemtag = readconfigfromdb('systemtag');
	$currency = readconfigfromdb('currency');

	$subject .= '[' . $systemtag . '] Interlets transactie';

	$content  = '-- Dit is een automatische mail, niet beantwoorden a.u.b. --' . $r . $r;

	$content  .= 'Er werd een interlets transactie ingegeven op de installatie van ' . $systemname;
	$content  .= ' met de volgende gegevens:' . $r . $r;

	$u_from = ($transaction['real_from']) ?: link_user($transaction['id_from'], null, false);
	$u_to = ($transaction['real_to']) ?: link_user($transaction['id_to'], null, false);

	$content .= 'Van: ' . $t . $t . $u_from . $r;
	$content .= 'Aan: ' . $t . $t . $u_from . $r;

	$content .= 'Omschrijving: ' . $t . $t . $transaction['description'] . $r;

	$currencyratio = readconfigfromdb('currencyratio');
	$meta = round($transaction['amount'] / $currencyratio, 4);

	$content .= 'Aantal: ' . $t . $transaction['amount'] . $currency . ', ofwel ' . $meta . ' LETS uren*, ' . $currencyratio . ' ' . $currency ' = 1 uur)' . $r . $r;
	$content .= 'Transactie id: ' . $t . $t . $transaction['transid'] . $r . $r;

	$content .= 'Je moet deze in je eigen systeem verder verwerken. ' . $r;
	$content .= 'Als dit niet mogelijk is moet je de kern van de andere groep verwittigen zodat ze de transactie aan hun kant annuleren.';

	sendemail($from, $to, $subject, $content);

	log_event($s_id, 'Mail', 'Transaction sent to ' . $to);
}

function mail_transaction($transaction)
{
	global $s_id, $base_url;

	$r = "\r\n";
	$t = "\t";

	$from = readconfigfromdb('from_address_transactions');
	$currency = readconfigfromdb('currency');

	$userfrom = readuser($transaction['id_from']);
	
	if($userfrom['accountrole'] != 'interlets')
	{
		$to = get_mailaddresses($transaction['id_from']);
	}

	$userto = readuser($transaction['id_to']);

	$userto_mail = get_mailaddresses($transaction['id_to']);

	$to .= ',' . $userto_mail;

	$systemtag = readconfigfromdb('systemtag');
	$currency = readconfigfromdb('currency');

	$u_from = ($transaction['real_from']) ?: link_user($transaction['id_from'], null, false);
	$u_to = ($transaction['real_to']) ?: link_user($transaction['id_to'], null, false);

	$subject .= '[' . $systemtag . '] ' . $transaction['amount'] . ' ' . $currency . ' van ';
	$subject .= $u_from . ' aan ' . $u_to;

	$content = '-- Dit is een automatische mail, niet beantwoorden a.u.b. --' . $r . $r;
	$content .= 'Notificatie nieuwe transactie' . $r . $r;
	$content .= 'Van: ' . $t . $t . $u_from . $r;
	$content .= 'Aan: ' . $t . $t . $u_to . $r;

	$content .= 'Omschrijving: ' . $t . $t . $transaction['description'] . $r;
	$content .= 'Aantal: ' . $t . $t . $transaction['amount'] . ' ' . $currency . $r . $r;
	$content .= 'Transactie id:' . $t . $t .$transaction['transid'] . $r;
	$content .= 'link: ' . $base_url . '/transactions.php?id=' . $transaction['id'] . $r;

	sendemail($from, $to, $subject, $content);

	log_event($s_id, 'Mail', 'Transaction sent to ' . $to);
}

function mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result,$admincc)
{
	$r = "\r\n";
	$t = "\t";

	$from = readconfigfromdb('from_address_transactions');

	$systemtag = readconfigfromdb('systemtag');
	$currency = readconfigfromdb('currency');

	$subject .= '['. $systemtag . '] Gefaalde interlets transactie ' . $transid;

	$userfrom = readuser($id_from);


	if($userfrom['accountrole'] != 'interlets')
	{
		$to = get_mailaddresses($id_from);
	}

	if($admincc)
	{
		$to .= ','. readconfigfromdb('admin');
	}

	$content  = '-- Dit is een automatische mail, niet beantwoorden a.u.b. --' . $r . $r;
	$content .= 'Je interlets transactie hieronder kon niet worden uitgevoerd om de volgende reden:' . $r . $r;

	switch ($result)
	{
		case 'SIGFAIL':
			$content .= 'De digitale handtekening was ongeldig, dit wijst op een fout in de instellingen van de installlatie.  Deze melding werd ook naar de site-beheerder verstuurd.';
			break;
		case 'EXPIRED':
			$content .= 'Na 4 dagen kon geen contact met de andere installatie gemaakt worden, probeer de transactie later opnieuw of verwittig de beheerder als dit blijft.';
			break;
		case 'NOUSER':
			$content .= 'De gebruiker met die letscode bestaat niet in de andere groep.  Controleer de letscode via de interlets-functies en probeer het eventueel opnieuw.';
		break;
	}
	$content .=  $r . $r . '--' . $r;

	$amount = $amount * readconfigfromdb('currencyratio');
	$amount = round($amount);

	$content .= 'Letscode: ' . $t . $letscode_to . $r;
	$content .= 'Voor: ' . $t . $description . $r;
	$content .= 'Aantal: ' . $t . $amount . $currency . $r . $r;
	$content .= 'Transactie id:' . $t . $transid . $r . $r . '--';;

	sendemail($from, $to, $subject, $content);
	log_event($s_id, 'Mail', 'Interlets failure sent to ' . $to);
}

function get_mailaddresses($uid)
{
	global $db;

	$addr = array();
	$st = $db->prepare('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'');

	$st->bindValue(1, $uid);
	$st->execute();

	while($row = $st->fetch())
	{
		$addr[] = $row['value'];
	}
	return implode(', ', $addr);
}
