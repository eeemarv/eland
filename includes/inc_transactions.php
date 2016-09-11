<?php

/**
 *
 */

function generate_transid()
{
	global $s_id;
	return substr(sha1($s_id . microtime()), 0, 12) . '_' . $s_id . '@' . $_SERVER['SERVER_NAME'];
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
function mail_mailtype_interlets_transaction($transaction)
{
	global $app;

	$from_user = link_user($transaction['id_from'], false, false);
	$to_group = link_user($transaction['id_to'], false, false);

	$to_user = $transaction['real_to'];

	$vars = [
		'copy'		=> false,
		'from_user' => $from_user,
		'to_user'	=> $to_user,
		'to_group'	=> $to_group,
		'amount'			=> $transaction['amount'],
		'amount_hours'		=> round($transaction['amount'] / readconfigfromdb('currencyratio'), 4),
		'transid'			=> $transaction['transid'],
		'description'		=> $transaction['description'],
		'group'				=> [
			'name'			=> readconfigfromdb('systemname'),
			'tag'			=> readconfigfromdb('systemtag'),
			'currency'		=> readconfigfromdb('currency'),
			'currencyratio'	=> readconfigfromdb('currencyratio'),
		],
	];

	$app['eland.task.mail']->queue([
		'to' 		=> $transaction['id_to'],
		'reply_to' 	=> 'admin',
		'template'	=> 'mailtype_interlets_transaction',
		'vars'		=> $vars,
	]);

	$vars['copy'] = true;

	$app['eland.task.mail']->queue([
		'to' 		=> $transaction['id_from'],
		'cc' 		=> 'admin',
		'template'	=> 'mailtype_interlets_transaction',
		'vars'		=> $vars,
	]);
}

/*
 *
 */
function mail_transaction($transaction, $remote_schema = null)
{
	global $app;

	$sch = isset($remote_schema) ? $remote_schema : $app['eland.this_group']->get_schema();

	$userfrom = readuser($transaction['id_from'], false, $sch);
	$userto = readuser($transaction['id_to'], false, $sch);

	$interlets = ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? 'interlets ' : '';

	$real_from = $transaction['real_from'] ?? '';
	$real_to = $transaction['real_to'] ?? '';

	$from_user = ($real_from) ? $real_from . ' [' . $userfrom['fullname'] . ']' : $userfrom['letscode'] . ' ' . $userfrom['name'];
	$to_user = ($real_to) ? $real_to . ' [' . $userto['fullname'] . ']' : $userto['letscode'] . ' ' . $userto['name'];

	$url = isset($remote_schema) ? $app['eland.protocol'] . $app['eland.groups']->get_host($sch) : $app['eland.base_url'];

	$vars = [
		'from_user' => $from_user,
		'to_user'	=> $to_user,
		'interlets'	=> ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? true : false,
		'amount'			=> $transaction['amount'],
		'transid'			=> $transaction['transid'],
		'description'		=> $transaction['description'],
		'transaction_url'	=> $url . '/transactions.php?id=' . $transaction['id'],
		'group'				=> [
			'name'			=> readconfigfromdb('systemname', $sch),
			'tag'			=> readconfigfromdb('systemtag', $sch),
			'currency'		=> readconfigfromdb('currency', $sch),
			'support'		=> readconfigfromdb('support', $sch),
		],
	];

	$t_schema = ($remote_schema) ? $remote_schema . '.' : '';

	$base_url = $app['eland.protocol'] . $app['eland.groups']->get_host($sch);

	if ($userfrom['accountrole'] != 'interlets' && ($userfrom['status'] == 1 || $userfrom['status'] == 2))
	{
		$app['eland.task.mail']->queue([
			'to' 		=> $userfrom['id'],
			'template'	=> 'transaction',
			'vars'		=> array_merge($vars, [
				'user' 			=> $userfrom,
				'url_login'		=> $base_url . '/login.php?login=' . $userfrom['letscode'],
			]),
		]);
	}

	if ($userto['accountrole'] != 'interlets' && ($userto['status'] == 1 || $userto == 2))
	{
		$app['eland.task.mail']->queue([
			'to' 		=> $t_schema . $userto['id'],
			'schema'	=> $sch,
			'template'	=> 'transaction',
			'vars'		=> array_merge($vars, [
				'user'		=> $userto,
				'url_login'	=> $base_url . '/login.php?login=' . $userto['letscode'],
			]),
		]);
	}
}
