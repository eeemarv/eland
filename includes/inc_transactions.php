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

/*
 *
 */

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

/*
 *
 */

function insert_transaction($transaction)
{
    global $db, $s_id, $currency;

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
		$transaction['amount'] . ' ' . $currency . ' from user ' .
		link_user($transaction['id_from'], null, false, true) . ' to user ' .
		link_user($transaction['id_to'], null, false, true));

	return $id;
}

/*
 *
 */
function mail_mail_interlets_transaction($transaction)
{
	global $s_id, $systemname, $systemtag, $currency;

	$r = "\r\n";
	$t = "\t";

	$userfrom = readuser($transaction['id_from']);

	$to = get_mailaddresses($transaction['id_to']);

	$subject .= '[' . $systemtag . '] Interlets transactie';

	$text  = '-- Dit is een automatische mail, niet beantwoorden a.u.b. --' . $r . $r;

	$text  .= 'Er werd een interlets transactie ingegeven op de installatie van ' . $systemname;
	$text  .= ' met de volgende gegevens:' . $r . $r;

	$u_from = ($transaction['real_from']) ?: link_user($transaction['id_from'], null, false);
	$u_to = ($transaction['real_to']) ?: link_user($transaction['id_to'], null, false);

	$text .= 'Van: ' . $t . $t . $u_from . $r;
	$text .= 'Aan: ' . $t . $t . $u_to . ', letscode: ' . $transaction['letscode_to'] . $r;

	$text .= 'Omschrijving: ' . $t . $transaction['description'] . $r;

	$currencyratio = readconfigfromdb('currencyratio');
	$meta = round($transaction['amount'] / $currencyratio, 4);

	$text .= 'Aantal: ' . $t . $transaction['amount'] . ' ' . $currency . ', ofwel ';
	$text .= $meta . ' LETS uren* (' . $currencyratio . ' ' . $currency . ' = 1 uur)' . $r . $r;
	$text .= 'Transactie id: ' . $t . $transaction['transid'] . $r . $r;

	$text .= 'Je moet deze in je eigen systeem verder verwerken.' . $r;
	$text .= 'Als dit niet mogelijk is, moet je de kern van de andere groep ';
	$text .= 'verwittigen zodat ze de transactie aan hun kant annuleren.';

	mail_q(array('to' => $to, 'subject' => $subject, 'text' => $text, 'reply-to' => readconfigfromdb('admin')));

	$subject .= ' [Kopie van bericht verzonden naar ' . $u_to . ']';
	$text .= $r . $r . '-- Dit bericht werd verzonden naar adres: ' . $to . ' -- ';
	$to = get_mailaddresses($transaction['id_from']);

	mail_q(array('to' => $to, 'subject' => $subject, 'text' => $text));
}

/*
 *
 */
function mail_transaction($transaction, $remote_schema = null)
{
	global $s_id, $base_url, $schema;

	$r = "\r\n";
	$t = "\t";

	$sch = (isset($remote_schema)) ? $remote_schema : $schema;

	$currency = readconfigfromdb('currency', $sch);

	$userfrom = readuser($transaction['id_from'], false, $sch);

	$to = array();

	if ($userfrom['accountrole'] != 'interlets' && ($userfrom['status'] == 1 || $userfrom['status'] == 2))
	{
		$to[] = get_mailaddresses($transaction['id_from'], $sch);
	}

	$userto = readuser($transaction['id_to'], false, $sch);

	if ($userto['accountrole'] != 'interlets' && ($userto['status'] == 1 || $userto == 2))
	{
		$to[] = get_mailaddresses($transaction['id_to'], $sch);
	}

	$interlets = ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? 'interlets ' : '';

	$to = implode(',', $to);

	$systemtag = readconfigfromdb('systemtag', $sch);
	$currency = readconfigfromdb('currency', $sch);

	$real_from = $transaction['real_from'];
	$real_to = $transaction['real_to'];

	$u_from = ($real_from) ? $real_from . ' [' . $userfrom['fullname'] . ']' : $userfrom['letscode'] . ' ' . $userfrom['name'];
	$u_to = ($real_to) ? $real_to . ' [' . $userto['fullname'] . ']' : $userto['letscode'] . ' ' . $userto['name'];

	$subject = $interlets . 'transactie, ' . $transaction['amount'] . ' ' . $currency . ' van ';
	$subject .= $u_from . ' aan ' . $u_to;

	$text = '-- Dit is een automatische mail, niet beantwoorden a.u.b. --' . $r . $r;
	$text .= 'Notificatie ' . $interlets . 'transactie' . $r . $r;
	$text .= 'Van: ' . $t . $t . $u_from . $r;
	$text .= 'Aan: ' . $t . $t . $u_to . $r;

	$text .= 'Omschrijving: ' . $t . $t . $transaction['description'] . $r;
	$text .= 'Aantal: ' . $t . $t . $transaction['amount'] . ' ' . $currency . $r . $r;
	$text .= 'Transactie id:' . $t . $t .$transaction['transid'] . $r;

	if (isset($remote_schema))
	{
		list($schemas, $domains) = get_schemas_domains(true);
		$url = $domains[$sch];
	}
	else
	{
		$url = $base_url;
	}

	$text .= 'link: ' . $url . '/transactions.php?id=' . $transaction['id'] . $r;

	mail_q(array('to' => $to, 'subject' => $subject, 'text' => $text, 'from_schema' => $sch));

	log_event(((isset($remote_schema)) ? '' : $s_id), 'mail', $subject, $sch);
}

/*
 *
 */

function mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result,$admincc)
{
	global $systemtag, $currency;

	$r = "\r\n";
	$t = "\t";

	$subject .= 'Gefaalde interlets transactie ' . $transid;

	$userfrom = readuser($id_from);


	if($userfrom['accountrole'] != 'interlets')
	{
		$to = get_mailaddresses($id_from);
	}

	if($admincc)
	{
		$to .= ','. readconfigfromdb('admin');
	}

	$text  = '-- Dit is een automatische mail, niet beantwoorden a.u.b. --' . $r . $r;
	$text .= 'Je interlets transactie hieronder kon niet worden uitgevoerd om de volgende reden:' . $r . $r;

	switch ($result)
	{
		case 'SIGFAIL':
			$text .= 'De digitale handtekening was ongeldig, dit wijst op een fout in de instellingen van de installlatie.  Deze melding werd ook naar de site-beheerder verstuurd.';
			break;
		case 'EXPIRED':
			$text .= 'Na 4 dagen kon geen contact met de andere installatie gemaakt worden, probeer de transactie later opnieuw of verwittig de beheerder als dit blijft.';
			break;
		case 'NOUSER':
			$text .= 'De gebruiker met die letscode bestaat niet in de andere groep.  Controleer de letscode via de interlets-functies en probeer het eventueel opnieuw.';
		break;
	}
	$text .=  $r . $r . '--' . $r;

	$amount = $amount * readconfigfromdb('currencyratio');
	$amount = round($amount);

	$text .= 'Letscode: ' . $t . $letscode_to . $r;
	$text .= 'Voor: ' . $t . $description . $r;
	$text .= 'Aantal: ' . $t . $amount . $currency . $r . $r;
	$text .= 'Transactie id:' . $t . $transid . $r . $r . '--';;

	mail_q(array('to' => $to, 'subject' => $subject, 'text' => $text));
}

/*
 *
 */

function get_mailaddresses($uid, $remote_schema = null)
{
	global $db;

	$s = ($remote_schema) ? $remote_schema . '.' : '';

	$addr = array();
	$st = $db->prepare('select c.value
		from ' . $s . 'contact c, ' . $s . 'type_contact tc, ' . $s . 'users u
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and u.id = c.id_user
			and u.status in (1, 2)
			and tc.abbrev = \'mail\'');

	$st->bindValue(1, $uid);
	$st->execute();

	while($row = $st->fetch())
	{
		$addr[] = $row['value'];
	}
	return implode(', ', $addr);
}
