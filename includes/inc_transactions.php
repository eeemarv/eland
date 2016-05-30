<?php

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
	log_event('debug','Signing ' . $tosign . ' : ' . $signature);
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

	log_event('trans', 'Transaction ' . $transaction['transid'] . ' saved: ' .
		$transaction['amount'] . ' ' . $currency . ' from user ' .
		link_user($transaction['id_from'], false, false, true) . ' to user ' .
		link_user($transaction['id_to'], false, false, true));

	return $id;
}

/*
 *
 */
function mail_mail_interlets_transaction($transaction)
{
	global $systemname, $systemtag, $currency;

	$r = "\r\n";
	$t = "\t";

	$to = getmailadr($transaction['id_to']);

	$subject .= '[' . $systemtag . '] Interlets transactie';

	$text  = '-- Dit is een automatische mail, niet beantwoorden a.u.b. --' . $r . $r;

	$text  .= 'Er werd een interlets transactie ingegeven op de installatie van ' . $systemname;
	$text  .= ' met de volgende gegevens:' . $r . $r;

	$u_from = ($transaction['real_from']) ?: link_user($transaction['id_from'], false, false);
	$u_to = ($transaction['real_to']) ?: link_user($transaction['id_to'], false, false);

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

	mail_q(array('to' => $to, 'subject' => $subject, 'text' => $text, 'reply_to' => 'admin'));

	$subject .= ' [Kopie van bericht verzonden naar ' . $u_to . ']';
	$text .= $r . $r . '-- Dit bericht werd verzonden naar adres: ' . $to . ' -- ';

	mail_q(array('to' => $transaction['id_from'], 'subject' => $subject, 'text' => $text, 'cc' => 'admin'));
}

/*
 *
 */
function mail_transaction($transaction, $remote_schema = null)
{
	global $base_url, $schema, $hosts;

	$r = "\r\n";
	$t = "\t";

	$sch = (isset($remote_schema)) ? $remote_schema : $schema;

	$currency = readconfigfromdb('currency', $sch);

	$userfrom = readuser($transaction['id_from'], false, $sch);
	$userto = readuser($transaction['id_to'], false, $sch);

	$interlets = ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? 'interlets ' : '';

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
		$url = $app_protocol . $hosts[$sch];
	}
	else
	{
		$url = $base_url;
	}

	$text .= 'link: ' . $url . '/transactions.php?id=' . $transaction['id'] . $r;

	$t_schema = ($remote_schema) ? $remote_schema . '.' : '';

	if ($userfrom['accountrole'] != 'interlets' && ($userfrom['status'] == 1 || $userfrom['status'] == 2))
	{
		mail_q(array('to' => $userfrom['id'], 'subject' => $subject, 'text' => $text));
	}

	if ($userto['accountrole'] != 'interlets' && ($userto['status'] == 1 || $userto == 2))
	{
		mail_q(array('to' => $t_schema . $userto['id'], 'subject' => $subject, 'text' => $text), false, $sch);
	}

	log_event('mail', $subject, $sch);
}

/*
 *
 */

function mail_failed_interlets($transid, $id_from, $amount, $description, $letscode_to, $result)
{
	global $systemtag, $currency;

	$r = "\r\n";
	$t = "\t";

	$subject .= 'Gefaalde interlets transactie ' . $transid;

	$userfrom = readuser($id_from);

	$to = array();

	if($userfrom['accountrole'] != 'interlets')
	{
		$to[] = $id_from;
	}

	if($admincc)
	{
		$to[] = 'admin';
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

	mail_q(array('to' => $to, 'subject' => $subject, 'text' => $text, 'cc' => 'admin'));
}

