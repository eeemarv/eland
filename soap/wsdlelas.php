<?php
$rootpath='../';
$page_access = 'anonymous';
require_once __DIR__ . '/../include/web.php';
require_once __DIR__ . '/../include/transactions.php';

if (!$app['config']->get('template_lets'))
{
	echo 'NO_ELAS_LETS';
	exit;
}

if (!$app['config']->get('interlets_en'))
{
	echo 'NO_INTERLETS';
	exit;
}

$server = new soap_server();
$server->configureWSDL('interletswsdl', 'urn:interletswsdl');

/*
 *
 */

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

$server->register('userbyname',
    ['apikey' => 'xsd:string', 'name' => 'xsd:string', 'hash' => 'xsd:string'],
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

$server->register('dopayment',
   ['apikey' => 'xsd:string', 'from' => 'xsd:string',
		'real_from' => 'xsd:string', 'to' => 'xsd:string',
		'description' => 'xsd:string', 'amount' => 'xsd:float',
		'transid' => 'xsd:string', 'signature' => 'xsd:string'],
   ['return' => 'xsd:string'],
   'urn:interletswsdl',
   'urn:interletswsdl#dopayment',
   'rpc',
   'encoded',
   'Commit an interlets transaction'
);

$post_data = file_get_contents('php://input');
$server->service($post_data);

/*
 *
 */

function gettoken($apikey)
{
	global $app;

	if ($app['config']->get('maintenance'))
	{
		$app['monolog']->debug('elas-soap: Transaction token request deferred (offline)');

		return 'OFFLINE';
	}

	$app['monolog']->debug('Token request');

	if(check_apikey($apikey, 'interlets'))
	{
		$token = 'elasv2' . substr(md5(microtime() . $app['this_group']->get_schema()), 0, 12);

		$key = $app['this_group']->get_schema() . '_token_' . $token;

		$app['predis']->set($key, $apikey);
		$app['predis']->expire($key, 600);

		$app['monolog']->debug('elas-soap: Token ' . $token . ' generated');

		return $token;
	}

	$app['monolog']->debug('elas-soap: apikey fail, apikey: ' . $apikey . ' no token generated');

	return '---';
}

/*
 *
 */

function dopayment($apikey, $from, $real_from, $to, $description, $amount, $transid, $signature)
{
	global $app;

	if ($app['config']->get('maintenance'))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' deferred (offline)');

		return 'OFFLINE';
	}

	// Possible status values are SUCCESS, FAILED, DUPLICATE and OFFLINE

	$app['monolog']->debug('Transaction request from: ' . $from . ' real from: ' . $real_from . ' to: ' . $to . ' description: "' . $description . '" amount: ' . $amount . ' transid: ' . $transid);

	if ($app['db']->fetchColumn('SELECT * FROM transactions WHERE transid = ?', [$transid]))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' is a duplicate');
		return 'DUPLICATE';
	}

	if (check_apikey($apikey, 'interlets'))
	{
		$app['monolog']->debug('Looking up Interlets user ' . $from);

		if ($fromuser = $app['db']->fetchAssoc('SELECT * FROM users WHERE letscode = ?', [$from]))
		{
			$app['monolog']->debug('Found Interlets fromuser ' . json_encode($fromuser));
		}
		else
		{
			$app['monolog']->debug('NOT found interlets fromuser ' . $from . ' transid: ' . $transid);
		}

		if ($touser = $app['db']->fetchAssoc('SELECT * FROM users WHERE letscode = ?', [$to]))
		{
			$app['monolog']->debug('Found Interlets touser ' . json_encode($touser));
		}
		else
		{
			$app['monolog']->debug('Not found Interlets touser ' . $to . ' transid: ' . $transid);
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
		];

		if (empty($fromuser['letscode']) || $fromuser['accountrole'] != 'interlets')
		{
			$app['monolog']->debug('elas-soap: Transaction ' . $transid . ', unknown FROM user (to:' . $to . ')');
			return 'NOUSER';
		}

		if (empty($touser['letscode']) || ($touser['status'] != 1 && $touser['status'] != 2))
		{
			$app['monolog']->debug('elas-soap: Transaction ' . $transid . ', unknown or invalid TO user');
			return 'NOUSER';
		}

		if (empty($transid))
		{
			$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' missing trans id (failed).');
			return 'FAILED';
		}

		if (empty($description))
		{
			$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' missing description (failed).');
			return 'FAILED';
		}

		$sigtest = sign_transaction($transaction, $fromuser['presharedkey']);

		if ($sigtest != $signature)
		{
			$app['monolog']->debug('elas-soap: Transaction ' . $transid . ', invalid signature');
			return 'SIGFAIL';
		}

		$transaction['amount'] = round($amount * $app['config']->get('currencyratio'));

		if ($transaction['amount'] < 1)
		{
			$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' amount ' . $transaction['amount'] . ' is lower than 1. (failed)');
			return 'FAILED';
		}

		if (($transaction['amount'] + $touser['saldo']) > $touser['maxlimit'])
		{
			$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' amount ' . $transaction['amount'] . ' failed. ' . link_user($touser, false, false) . ' over maxlimit.');
			return 'FAILED';
		}

		unset($transaction['letscode_to']);

		if($id = insert_transaction($transaction))
		{
			$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' processed (success)');
			$transaction['id'] = $id;
			mail_transaction($transaction);

			return 'SUCCESS';
		}

		$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' failed');

		return 'FAILED';
	}

	$app['monolog']->debug('elas-soap: APIKEY failed for Transaction ' . $transid . ' apikey: ' . $apikey);

	return 'APIKEYFAIL';
}

/*
 *
 */

function userbyletscode($apikey, $letscode)
{
	global $app;

	$app['monolog']->debug('Lookup request for ' . $letscode);

	if ($app['config']->get('maintenance'))
	{
		return 'OFFLINE';
	}

	if(check_apikey($apikey,'interlets'))
	{
		$user = $app['db']->fetchAssoc('SELECT * FROM users WHERE letscode = ?', [$letscode]);

		if($user['name'] == '')
		{
			return 'Onbekend';
		}
		else
		{
			return $user['name'];
		}
	}

	$app['monolog']->debug('Apikey fail, apikey: ' . $apikey . ' (lookup request for letscode ' . $letscode . ')');

	return '---';
}

/*
 *
 */

function userbyname($apikey, $name)
{
	global $app;

	$app['monolog']->debug('Lookup request for user ' . $name);

	if ($app['config']->get('maintenance'))
	{
		return 'OFFLINE';
	}

	if(check_apikey($apikey, 'interlets'))
	{
		$user = $app['db']->fetchAssoc('select * from users where name ilike ?', ['%' . $name . '%']);

		return ($user['name']) ? $user['letscode'] : 'Onbekend';
	}

	$app['monolog']->debug('Apikey fail, apikey: ' . $apikey . ' (lookup request for name ' . $name . ')');

	return '---';
}

/*
 *
 */

function getstatus($apikey)
{
	global $app;

	if ($app['config']->get('maintenance'))
	{
		return 'OFFLINE';
	}

	if (check_apikey($apikey, 'interlets'))
	{
		return 'OK - eLAND';
	}

	$app['monolog']->debug('Apikey fail, apikey: ' . $apikey . ' (lookup request for status)');

	return 'APIKEYFAIL';
}

/**
 *
 */

function apiversion($apikey)
{
	global $app;

	if ($app['config']->get('maintenance'))
	{
		return 'OFFLINE';
	}

	if(check_apikey($apikey, 'interlets'))
	{
		return 1200; //soapversion;
	}

	$app['monolog']->debug('Apikey fail, apikey: ' . $apikey . ' (lookup request for apiversion)');

	return 'APIKEYFAIL';
}

/**
 *
 */

function check_apikey($apikey, $type)
{
	global $app;

	return ($app['db']->fetchColumn('select apikey
		from apikeys
		where apikey = ?
		and type = ?', [$apikey, $type])) ? true : false;
}

