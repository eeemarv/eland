<?php

function generate_transid()
{
	global $app;
	return substr(sha1($app['s_id'] . microtime()), 0, 12) . '_' . $app['s_id'] . '@' . $_SERVER['SERVER_NAME'];
}

function sign_transaction($transaction, $sharedsecret)
{
	global $app;

	$amount = (float) $transaction['amount'];
	$amount = $amount * 100;
	$amount = round($amount);
	$tosign = $sharedsecret . $transaction['transid'] . strtolower($transaction['letscode_to']) . $amount;
	$signature = sha1($tosign);
	$app['monolog']->debug('Signing ' . $tosign . ' : ' . $signature,
		['schema' => $app['tschema']]);

	return $signature;
}

function insert_transaction($transaction)
{
	global $app, $s_master;

	$transaction['creator'] = $s_master ? 0 : ($app['s_id'] ? $app['s_id'] : 0);
    $transaction['cdate'] = gmdate('Y-m-d H:i:s');

	$app['db']->beginTransaction();

	try
	{
		$app['db']->insert($app['tschema'] . '.transactions', $transaction);
		$id = $app['db']->lastInsertId($app['tschema'] . '.transactions_id_seq');
		$app['db']->executeUpdate('update ' . $app['tschema'] . '.users set saldo = saldo + ? where id = ?', [$transaction['amount'], $transaction['id_to']]);
		$app['db']->executeUpdate('update ' . $app['tschema'] . '.users set saldo = saldo - ? where id = ?', [$transaction['amount'], $transaction['id_from']]);
		$app['db']->commit();

	}
	catch(Exception $e)
	{
		$app['db']->rollback();
		throw $e;
		return false;
	}

	$app['user_cache']->clear($transaction['id_to'], $app['tschema']);
	$app['user_cache']->clear($transaction['id_from'], $app['tschema']);

	$app['autominlimit']->init($app['tschema'])
		->process($transaction['id_from'],
		$transaction['id_to'],
		$transaction['amount']);

	$app['monolog']->info('Transaction ' . $transaction['transid'] . ' saved: ' .
		$transaction['amount'] . ' ' .
		$app['config']->get('currency', $app['tschema']) .
		' from user ' .
		link_user($transaction['id_from'], $app['tschema'], false, true) . ' to user ' .
		link_user($transaction['id_to'], $app['tschema'], false, true),
		['schema' => $app['tschema']]);

	return $id;
}

/*
 *
 */
function mail_mailtype_interlets_transaction($transaction)
{
	global $app;

	$from_user = link_user($transaction['id_from'], $app['tschema'], false);
	$to_group = link_user($transaction['id_to'], $app['tschema'], false);

	$to_user = $transaction['real_to'];

	$vars = [
		'support_url'	=> $app['base_url'] . '/support.php?src=p',
		'copy'			=> false,
		'from_user' 	=> $from_user,
		'to_user'		=> $to_user,
		'to_group'		=> $to_group,
		'amount'		=> $transaction['amount'],
		'amount_hours'	=> round($transaction['amount'] / $app['config']->get('currencyratio', $app['tschema']), 4),
		'transid'		=> $transaction['transid'],
		'description'	=> $transaction['description'],
		'group'			=> $app['template_vars']->get($app['tschema']),
	];

	$app['queue.mail']->queue([
		'schema'	=> $app['tschema'],
		'to' 		=> $app['mail_addr_user']->get($transaction['id_to'], $app['tschema']),
		'reply_to' 	=> $app['mail_addr_system']->get_admin($app['tschema']),
		'template'	=> 'mailtype_interlets_transaction',
		'vars'		=> $vars,
	], 9000);

	$vars['copy'] = true;

	$app['queue.mail']->queue([
		'schema'	=> $app['tschema'],
		'to' 		=> $app['mail_addr_user']->get($transaction['id_from'], $app['tschema']),
		'cc' 		=> $app['mail_addr_system']->get_admin($app['tschema']),
		'template'	=> 'mailtype_interlets_transaction',
		'vars'		=> $vars,
	], 9000);
}

/*
 *
 */
function mail_transaction($transaction, $remote_schema = null)
{
	global $app;

	$sch = isset($remote_schema) ? $remote_schema : $app['tschema'];

	$userfrom = $app['user_cache']->get($transaction['id_from'], $sch);
	$userto = $app['user_cache']->get($transaction['id_to'], $sch);

	$interlets = ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? 'interlets ' : '';

	$real_from = $transaction['real_from'] ?? '';
	$real_to = $transaction['real_to'] ?? '';

	$from_user = $real_from ? $real_from . ' [' . $userfrom['fullname'] . ']' : $userfrom['letscode'] . ' ' . $userfrom['name'];
	$to_user = $real_to ? $real_to . ' [' . $userto['fullname'] . ']' : $userto['letscode'] . ' ' . $userto['name'];

	$url = isset($remote_schema) ? $app['protocol'] . $app['groups']->get_host($sch) : $app['base_url'];

	$vars = [
		'support_url'		=> $url . '/support.php?src=p',
		'from_user' 		=> $from_user,
		'to_user'			=> $to_user,
		'interlets'			=> ($userfrom['accountrole'] == 'interlets' || $userto['accountrole'] == 'interlets') ? true : false,
		'amount'			=> $transaction['amount'],
		'transid'			=> $transaction['transid'],
		'description'		=> $transaction['description'],
		'transaction_url'	=> $url . '/transactions.php?id=' . $transaction['id'],
		'group'				=> $app['template_vars']->get($sch),
	];

	$t_schema = $remote_schema ? $remote_schema . '.' : '';

	$base_url = $app['protocol'] . $app['groups']->get_host($sch);

	if ($userfrom['accountrole'] != 'interlets' && ($userfrom['status'] == 1 || $userfrom['status'] == 2))
	{
		$app['queue.mail']->queue([
			'schema'	=> $app['tschema'],
			'to' 		=> $app['mail_addr_user']->get($userfrom['id'], $app['tschema']),
			'template'	=> 'transaction',
			'vars'		=> array_merge($vars, [
				'user' 			=> $userfrom,
				'url_login'		=> $base_url . '/login.php?login=' . $userfrom['letscode'],
			]),
		], 9000);
	}

	if ($userto['accountrole'] != 'interlets' && ($userto['status'] == 1 || $userto['status'] == 2))
	{
		$app['queue.mail']->queue([
			'to' 		=> $app['mail_addr_user']->get($userto['id'], $sch),
			'schema'	=> $sch,
			'template'	=> 'transaction',
			'vars'		=> array_merge($vars, [
				'user'		=> $userto,
				'url_login'	=> $base_url . '/login.php?login=' . $userto['letscode'],
			]),
		], 9000);
	}
}
