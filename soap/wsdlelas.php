<?php

if (!$app['config']->get('template_lets', $app['tschema']))
{
	echo 'NO_ELAS_TIMEBANK';
	exit;
}

if (!$app['config']->get('interlets_en', $app['tschema']))
{
	echo 'NO_INTERSYSTEM';
	exit;
}

$server = new soap_server();
$server->configureWSDL('interletswsdl', 'urn:interletswsdl');

$server->register('gettoken',
    ['apikey' => 'xsd:string'],
    ['return' => 'xsd:string'],
    'urn:interletswsdl',
    'urn:interletswsdl#gettoken',
    'rpc',
    'encoded',
    'Get a login token'
);

$server->register('userbyletscode',
    ['apikey' => 'xsd:string', 'letscode' => 'xsd:string'],
    ['return' => 'xsd:string'],
    'urn:interletswsdl',
    'urn:interletswsdl#userbyletscode',
    'rpc',
    'encoded',
    'Get the user'
);

$server->register('userbyname', [
	'apikey'	=> 'xsd:string',
	'name' 		=> 'xsd:string',
	'hash' 		=> 'xsd:string',
	],
	['return' => 'xsd:string'],
	'urn:interletswsdl',
	'urn:interletswsdl#userbyletscode',
	'rpc',
	'encoded',
	'Get the user'
);

$server->register('getstatus',
	['apikey' => 'xsd:string'],
	['return' => 'xsd:string'],
	'urn:interletswsdl',
	'urn:interletswsdl#getstatus',
	'rpc',
	'encoded',
	'Get the eLAS status'
);

$server->register('apiversion',
	['apikey' => 'xsd:string'],
	['return' => 'xsd:string'],
	'urn:interletswsdl',
	'urn:interletswsdl#apiversion',
	'rpc',
	'encoded',
	'Get the eLAS SOAP API version'
);

$server->register('dopayment',[
	'apikey' 	=> 'xsd:string',
	'from' 		=> 'xsd:string',
	'real_from'	=> 'xsd:string',
	'to' 		=> 'xsd:string',
	'description' => 'xsd:string',
	'amount' 	=> 'xsd:float',
	'transid' 	=> 'xsd:string',
	'signature' => 'xsd:string',
	],
	['return' => 'xsd:string'],
	'urn:interletswsdl',
	'urn:interletswsdl#dopayment',
	'rpc',
	'encoded',
	'Commit an interlets transaction'
);

$server->service(file_get_contents('php://input'));

function gettoken($apikey)
{
	global $app;

	if ($app['config']->get('maintenance', $app['tschema']))
	{
		$app['monolog']->debug('elas-soap: Transaction token request deferred (offline)',
			['schema' => $app['tschema']]);

		return 'OFFLINE';
	}

	$app['monolog']->debug('Token request',
		['schema' => $app['tschema']]);

	if(check_apikey($apikey, 'interlets'))
	{
		$token = 'elasv2' . substr(md5(microtime() . $app['tschema']), 0, 12);

		$key = $app['tschema'] . '_token_' . $token;

		$app['predis']->set($key, $apikey);
		$app['predis']->expire($key, 600);

		$app['monolog']->debug('elas-soap: Token ' . $token .
			' generated', ['schema' => $app['tschema']]);

		return $token;
	}

	$app['monolog']->debug('elas-soap: apikey fail, apikey: ' . $apikey .
		' no token generated', ['schema' => $app['tschema']]);

	return '---';
}

function dopayment($apikey, $from, $real_from, $to, $description, $amount, $transid, $signature)
{
	global $app;

	if ($app['config']->get('maintenance', $app['tschema']))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' deferred (offline)', ['schema' => $app['tschema']]);

		return 'OFFLINE';
	}

	// Possible status values are SUCCESS, FAILED, DUPLICATE and OFFLINE

	$app['monolog']->debug('Transaction request from: ' . $from .
		' real from: ' . $real_from . ' to: ' . $to .
		' description: "' . $description . '" amount: ' .
		$amount . ' transid: ' . $transid, ['schema' => $app['tschema']]);

	if ($app['db']->fetchColumn('select *
		from ' . $app['tschema'] . '.transactions
		where transid = ?', [$transid]))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' is a duplicate', ['schema' => $app['tschema']]);
		return 'DUPLICATE';
	}

	if (!check_apikey($apikey, 'interlets'))
	{
		$app['monolog']->debug('elas-soap: APIKEY failed for Transaction ' . $transid .
			' apikey: ' . $apikey, ['schema' => $app['tschema']]);

		return 'APIKEYFAIL';
	}

	$app['monolog']->debug('Looking up interSystem user ' .
		$from, ['schema' => $app['tschema']]);

	if ($fromuser = get_user_by_letscode($from))
	{
		$app['monolog']->debug('Found interSystem fromuser ' .
			json_encode($fromuser), ['schema' => $app['tschema']]);
	}
	else
	{
		$app['monolog']->debug('NOT found interSystem fromuser ' . $from .
			' transid: ' . $transid, ['schema' => $app['tschema']]);
	}

	if ($touser = get_user_by_letscode($to))
	{
		$app['monolog']->debug('Found InterSystem touser ' .
			json_encode($touser), ['schema' => $app['tschema']]);
	}
	else
	{
		$app['monolog']->debug('Not found InterSystem touser ' . $to . ' transid: ' .
			$transid, ['schema' => $app['tschema']]);
	}

	$transaction = [
		'transid'		=> $transid,
		'date' 			=> date('Y-m-d H:i:s'),
		'description' 	=> $description,
		'id_from' 		=> $fromuser['id'],
		'real_from' 	=> $real_from,
		'id_to' 		=> $touser['id'],
		'amount' 		=> $amount,
		'letscode_to' 	=> $touser['letscode'],
		'creator'		=> 0,
	];

	if (empty($fromuser['letscode']) || $fromuser['accountrole'] != 'interlets')
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			', unknown FROM user (to:' . $to . ')', ['schema' => $app['tschema']]);
		return 'NOUSER';
	}

	if (empty($touser['letscode']) || ($touser['status'] != 1 && $touser['status'] != 2))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			', unknown or invalid TO user', ['schema' => $app['tschema']]);
		return 'NOUSER';
	}

	if (empty($transid))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' missing trans id (failed).', ['schema' => $app['tschema']]);
		return 'FAILED';
	}

	if (empty($description))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' missing description (failed).', ['schema' => $app['tschema']]);
		return 'FAILED';
	}

	$sigtest = $app['transaction']->sign($transaction, $fromuser['presharedkey'], $app['tschema']);

	if ($sigtest != $signature)
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			', invalid signature', ['schema' => $app['tschema']]);
		return 'SIGFAIL';
	}

	$transaction['amount'] = round($amount * $app['config']->get('currencyratio', $app['tschema']));

	if ($transaction['amount'] < 1)
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' amount ' .
			$transaction['amount'] . ' is lower than 1. (failed)',
			['schema' => $app['tschema']]);
		return 'FAILED';
	}

	if (($transaction['amount'] + $touser['saldo']) > $touser['maxlimit'])
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' amount ' . $transaction['amount'] . ' failed. ' .
			$app['account']->str_id($touser['id'], $app['tschema']) .
			' over maxlimit.', ['schema' => $app['tschema']]);
		return 'FAILED';
	}

	unset($transaction['letscode_to']);

	if($id = $app['transaction']->insert($transaction, $app['tschema']))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' processed (success)',
			['schema' => $app['tschema']]);
		$transaction['id'] = $id;

		// from eLAS interSystem
		$app['mail_transaction']->queue($transaction, $app['tschema']);

		return 'SUCCESS';
	}

	$app['monolog']->debug('elas-soap: Transaction ' . $transid .
		' failed', ['schema' => $app['tschema']]);

	return 'FAILED';
}

function userbyletscode($apikey, $letscode)
{
	global $app;

	$app['monolog']->debug('Lookup request for ' .
		$letscode, ['schema' => $app['tschema']]);

	if ($app['config']->get('maintenance', $app['tschema']))
	{
		return 'OFFLINE';
	}

	if(!check_apikey($apikey,'interlets'))
	{
		$app['monolog']->debug('Apikey fail, apikey: ' . $apikey .
			' (lookup request for letscode ' .
			$letscode . ')', ['schema' => $app['tschema']]);

		return '---';
	}

	$user = get_user_by_letscode($letscode);

	if ($user['status'] != 1 && $user['status'] != 2)
	{
		$app['monolog']->debug('User not active (lookup request for letscode ' .
			$letscode . ')', ['schema' => $app['tschema']]);
		return 'Onbekend';
	}

	if(!$user['name'])
	{
		return 'Onbekend';
	}

	return $user['name'];
}

function userbyname($apikey, $name)
{
	global $app;

	$app['monolog']->debug('Lookup request for user ' .
		$name, ['schema' => $app['tschema']]);

	if ($app['config']->get('maintenance', $app['tschema']))
	{
		return 'OFFLINE';
	}

	if(!check_apikey($apikey, 'interlets'))
	{
		$app['monolog']->debug('Apikey fail, apikey: ' . $apikey .
			' (lookup request for name ' .
			$name . ')', ['schema' => $app['tschema']]);
		return '---';
	}

	$letscode = $app['db']->fetchColumn('select letscode
		from ' . $app['tschema'] . '.users
		where status in (1, 2)
			and name ilike ?', ['%' . $name . '%']);
	return $letscode ?? 'Onbekend';
}

function getstatus($apikey)
{
	global $app;

	if ($app['config']->get('maintenance', $app['tschema']))
	{
		return 'OFFLINE';
	}

	if (check_apikey($apikey, 'interlets'))
	{
		return 'OK - eLAND';
	}

	$app['monolog']->debug('Apikey fail, apikey: ' . $apikey .
		' (lookup request for status)', ['schema' => $app['tschema']]);

	return 'APIKEYFAIL';
}

function apiversion($apikey)
{
	global $app;

	if ($app['config']->get('maintenance', $app['tschema']))
	{
		return 'OFFLINE';
	}

	if(check_apikey($apikey, 'interlets'))
	{
		return 1200; //soapversion;
	}

	$app['monolog']->debug('Apikey fail, apikey: ' . $apikey .
		' (lookup request for apiversion)',
		['schema' => $app['tschema']]);

	return 'APIKEYFAIL';
}

function check_apikey($apikey, $type)
{
	global $app;

	return $app['db']->fetchColumn('select apikey
		from ' . $app['tschema'] . '.apikeys
		where apikey = ?
		and type = ?', [trim($apikey), trim($type)]) ? true : false;
}

function get_user_by_letscode(string $letscode)
{
	global $app;

	$letscode = trim($letscode);
	[$letscode] = explode(' ', $letscode);

	return $app['db']->fetchAssoc('select *
		from ' . $app['tschema'] . '.users
		where letscode = ?', [$letscode]);
}
