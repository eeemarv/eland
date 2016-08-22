<?php

/**
 *
 */

function generate_transid()
{
	global $app, $s_id;
	return substr(sha1($s_id .microtime()), 0, 12) . '_' . $s_id . '@' . $app['eland.base_url'];
}

/*
 *
 */

function sign_transaction($transaction, $sharedsecret)
{
	global $app;

	$amount = (float) $transaction['amount'];
	$amount = $amount * 100;
	$amount = round($amount);
	$tosign = $sharedsecret . $transaction['transid'] . strtolower($transaction['letscode_to']) . $amount;
	$signature = sha1($tosign);
	$app['monolog']->debug('Signing ' . $tosign . ' : ' . $signature);

	return $signature;
}

/*
 *
 */

function insert_transaction($transaction)
{
    global $app, $s_id, $s_master;

	$transaction['creator'] = ($s_master) ? 0 : (($s_id) ? $s_id : 0);
    $transaction['cdate'] = gmdate('Y-m-d H:i:s');

	$app['db']->beginTransaction();

	try
	{
		$app['db']->insert('transactions', $transaction);
		$id = $app['db']->lastInsertId('transactions_id_seq');
		$app['db']->executeUpdate('update users set saldo = saldo + ? where id = ?', [$transaction['amount'], $transaction['id_to']]);
		$app['db']->executeUpdate('update users set saldo = saldo - ? where id = ?', [$transaction['amount'], $transaction['id_from']]);
		$app['db']->commit();

	}
	catch(Exception $e)
	{
		$app['db']->rollback();
		throw $e;
		return false;
	}

	$to_user = readuser($transaction['id_to'], true);
	$from_user = readuser($transaction['id_from'], true);

	error_log('jmqsfjmqskdjmqsfqsmf -- '  . http_build_query($transaction));

	$app['eland.task.autominlimit']->queue([
		'from_id'	=> $transaction['id_from'],
		'to_id'		=> $transaction['id_to'],
		'amount'	=> $transaction['amount'],
		'schema'	=> $app['eland.this_group']->get_schema(),
	]);

	$app['monolog']->info('Transaction ' . $transaction['transid'] . ' saved: ' .
		$transaction['amount'] . ' ' . readconfigfromdb('currency') . ' from user ' .
		link_user($transaction['id_from'], false, false, true) . ' to user ' .
		link_user($transaction['id_to'], false, false, true));

	return $id;
}

/*
 *
 */
function mail_mail_interlets_transaction($transaction)
{
	global $app;

	$r = "\r\n";
	$t = "\t";

	$to = getmailadr($transaction['id_to']);

	$subject .= 'Interlets transactie';

	$text  = '-- Dit is een automatische mail, niet beantwoorden a.u.b. --' . $r . $r;

	$text  .= 'Er werd een interlets transactie ingegeven op de installatie van ' . readconfigfromdb('systemname');
	$text  .= ' met de volgende gegevens:' . $r . $r;

	$u_from = ($transaction['real_from']) ?: link_user($transaction['id_from'], false, false);
	$u_to = ($transaction['real_to']) ?: link_user($transaction['id_to'], false, false);

	$text .= 'Van: ' . $t . $t . $u_from . $r;
	$text .= 'Aan: ' . $t . $t . $u_to . ', letscode: ' . $transaction['letscode_to'] . $r;

	$text .= 'Omschrijving: ' . $t . $transaction['description'] . $r;

	$currencyratio = readconfigfromdb('currencyratio');
	$meta = round($transaction['amount'] / $currencyratio, 4);

	$text .= 'Aantal: ' . $t . $transaction['amount'] . ' ' . readconfigfromdb('currency') . ', ofwel ';
	$text .= $meta . ' LETS uren* (' . $currencyratio . ' ' . readconfigfromdb('currency') . ' = 1 uur)' . $r . $r;
	$text .= 'Transactie id: ' . $t . $transaction['transid'] . $r . $r;

	$text .= 'Je moet deze in je eigen systeem verder verwerken.' . $r;
	$text .= 'Als dit niet mogelijk is, moet je de kern van de andere groep ';
	$text .= 'verwittigen zodat ze de transactie aan hun kant annuleren.';

	$app['eland.task.mail']->queue(['to' => $to, 'subject' => $subject, 'text' => $text, 'reply_to' => 'admin']);

	$subject .= ' [Kopie van bericht verzonden naar ' . $u_to . ']';
	$text .= $r . $r . '-- Dit bericht werd verzonden naar adres: ' . $to . ' -- ';

	$app['eland.task.mail']->queue(['to' => $transaction['id_from'], 'subject' => $subject, 'text' => $text, 'cc' => 'admin']);
}

/*
 *
 */
function mail_transaction($transaction, $remote_schema = null)
{
	global $app;

	$r = "\r\n";
	$t = "\t";

	$sch = (isset($remote_schema)) ? $remote_schema : $app['eland.this_group']->get_schema();

	$currency = readconfigfromdb('currency', $sch);

	$userfrom = readuser($transaction['id_from'], false, $sch);
	$userto = readuser($transaction['id_to'], false, $sch);

	$interlets = ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? 'interlets ' : '';

	$real_from = $transaction['real_from'] ?? '';
	$real_to = $transaction['real_to'] ?? '';

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
		$url = $app['eland.protocol'] . $app['eland.groups']->get_host($sch);
	}
	else
	{
		$url = $app['eland.base_url'];
	}

	$text .= 'link: ' . $url . '/transactions.php?id=' . $transaction['id'] . $r;

	$t_schema = ($remote_schema) ? $remote_schema . '.' : '';

	if ($userfrom['accountrole'] != 'interlets' && ($userfrom['status'] == 1 || $userfrom['status'] == 2))
	{
		$app['eland.task.mail']->queue(['to' => $userfrom['id'], 'subject' => $subject, 'text' => $text]);
	}

	if ($userto['accountrole'] != 'interlets' && ($userto['status'] == 1 || $userto == 2))
	{
		$app['eland.task.mail']->queue([
			'to' => $t_schema . $userto['id'],
			'subject' => $subject,
			'text' => $text,
			'schema'	=> $sch,
		]);
	}
}
