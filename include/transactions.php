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

	$app['user_cache']->clear($transaction['id_to']);
	$app['user_cache']->clear($transaction['id_from']);

	$app['autominlimit']->init()
		->process($transaction['id_from'], $transaction['id_to'], $transaction['amount']);

	$app['monolog']->info('Transaction ' . $transaction['transid'] . ' saved: ' .
		$transaction['amount'] . ' ' .
		$app['config']->get('currency', $app['this_group']->get_schema()) .
		' from user ' .
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
		'amount_hours'		=> round($transaction['amount'] / $app['config']->get('currencyratio', $app['this_group']->get_schema()), 4),
		'transid'			=> $transaction['transid'],
		'description'		=> $transaction['description'],
		'group'				=> [
			'name'			=> $app['config']->get('systemname', $app['this_group']->get_schema()),
			'tag'			=> $app['config']->get('systemtag', $app['this_group']->get_schema()),
			'currency'		=> $app['config']->get('currency', $app['this_group']->get_schema()),
			'currencyratio'	=> $app['config']->get('currencyratio', $app['this_group']->get_schema()),
		],
	];

	$app['queue.mail']->queue([
		'to' 		=> $transaction['id_to'],
		'reply_to' 	=> 'admin',
		'template'	=> 'mailtype_interlets_transaction',
		'vars'		=> $vars,
	]);

	$vars['copy'] = true;

	$app['queue.mail']->queue([
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

	$sch = isset($remote_schema) ? $remote_schema : $app['this_group']->get_schema();

	$userfrom = $app['user_cache']->get($transaction['id_from'], $sch);
	$userto = $app['user_cache']->get($transaction['id_to'], $sch);

	$interlets = ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? 'interlets ' : '';

	$real_from = $transaction['real_from'] ?? '';
	$real_to = $transaction['real_to'] ?? '';

	$from_user = ($real_from) ? $real_from . ' [' . $userfrom['fullname'] . ']' : $userfrom['letscode'] . ' ' . $userfrom['name'];
	$to_user = ($real_to) ? $real_to . ' [' . $userto['fullname'] . ']' : $userto['letscode'] . ' ' . $userto['name'];

	$url = isset($remote_schema) ? $app['protocol'] . $app['groups']->get_host($sch) : $app['base_url'];

	$vars = [
		'from_user' => $from_user,
		'to_user'	=> $to_user,
		'interlets'	=> ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? true : false,
		'amount'			=> $transaction['amount'],
		'transid'			=> $transaction['transid'],
		'description'		=> $transaction['description'],
		'transaction_url'	=> $url . '/transactions.php?id=' . $transaction['id'],
		'group'				=> [
			'name'			=> $app['config']->get('systemname', $sch),
			'tag'			=> $app['config']->get('systemtag', $sch),
			'currency'		=> $app['config']->get('currency', $sch),
			'support'		=> explode(',', $app['config']->get('support', $sch)),
		],
	];

	$t_schema = ($remote_schema) ? $remote_schema . '.' : '';

	$base_url = $app['protocol'] . $app['groups']->get_host($sch);

	if ($userfrom['accountrole'] != 'interlets' && ($userfrom['status'] == 1 || $userfrom['status'] == 2))
	{
		$app['queue.mail']->queue([
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
		$app['queue.mail']->queue([
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
