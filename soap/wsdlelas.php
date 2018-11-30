<?php
$rootpath='../';
$page_access = 'anonymous';
require_once __DIR__ . '/../include/web.php';
require_once __DIR__ . '/../include/transactions.php';

if (!$app['config']->get('template_lets', $tschema))
{
	echo 'NO_ELAS_TIMEBANK';
	exit;
}

if (!$app['config']->get('interlets_en', $tschema))
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

function gettoken($apikey)
{
	global $app;

	$tschema = $app['this_group']->get_schema();

	if ($app['config']->get('maintenance', $tschema))
	{
		$app['monolog']->debug('elas-soap: Transaction token request deferred (offline)',
			['schema' => $tschema]);

		return 'OFFLINE';
	}

	$app['monolog']->debug('Token request', ['schema' => $tschema]);

	if(check_apikey($apikey, 'interlets'))
	{
		$token = 'elasv2' . substr(md5(microtime() . $tschema), 0, 12);

		$key = $tschema . '_token_' . $token;

		$app['predis']->set($key, $apikey);
		$app['predis']->expire($key, 600);

		$app['monolog']->debug('elas-soap: Token ' . $token .
			' generated', ['schema' => $tschema]);

		return $token;
	}

	$app['monolog']->debug('elas-soap: apikey fail, apikey: ' . $apikey .
		' no token generated', ['schema' => $tschema]);

	return '---';
}

function dopayment($apikey, $from, $real_from, $to, $description, $amount, $transid, $signature)
{
	global $app;

	$tschema = $app['this_group']->get_schema();

	if ($app['config']->get('maintenance', $tschema))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' deferred (offline)', ['schema' => $tschema]);

		return 'OFFLINE';
	}

	// Possible status values are SUCCESS, FAILED, DUPLICATE and OFFLINE

	$app['monolog']->debug('Transaction request from: ' . $from .
		' real from: ' . $real_from . ' to: ' . $to .
		' description: "' . $description . '" amount: ' .
		$amount . ' transid: ' . $transid, ['schema' => $tschema]);

	if ($app['db']->fetchColumn('select *
		from ' . $tschema . '.transactions
		where transid = ?', [$transid]))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' is a duplicate', ['schema' => $tschema]);
		return 'DUPLICATE';
	}

	if (!check_apikey($apikey, 'interlets'))
	{
		$app['monolog']->debug('elas-soap: APIKEY failed for Transaction ' . $transid .
			' apikey: ' . $apikey, ['schema' => $tschema]);

		return 'APIKEYFAIL';
	}

	$app['monolog']->debug('Looking up interSystem user ' .
		$from, ['schema' => $tschema]);

	if ($fromuser = get_user_by_letscode($from))
	{
		$app['monolog']->debug('Found interSystem fromuser ' .
			json_encode($fromuser), ['schema' => $tschema]);
	}
	else
	{
		$app['monolog']->debug('NOT found interSystem fromuser ' . $from .
			' transid: ' . $transid, ['schema' => $tschema]);
	}

	if ($touser = get_user_by_letscode($to))
	{
		$app['monolog']->debug('Found InterSystem touser ' .
			json_encode($touser), ['schema' => $tschema]);
	}
	else
	{
		$app['monolog']->debug('Not found InterSystem touser ' . $to . ' transid: ' .
			$transid, ['schema' => $tschema]);
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
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			', unknown FROM user (to:' . $to . ')', ['schema' => $tschema]);
		return 'NOUSER';
	}

	if (empty($touser['letscode']) || ($touser['status'] != 1 && $touser['status'] != 2))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			', unknown or invalid TO user', ['schema' => $tschema]);
		return 'NOUSER';
	}

	if (empty($transid))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' missing trans id (failed).', ['schema' => $tschema]);
		return 'FAILED';
	}

	if (empty($description))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' missing description (failed).', ['schema' => $tschema]);
		return 'FAILED';
	}

	$sigtest = sign_transaction($transaction, $fromuser['presharedkey']);

	if ($sigtest != $signature)
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			', invalid signature', ['schema' => $tschema]);
		return 'SIGFAIL';
	}

	$transaction['amount'] = round($amount * $app['config']->get('currencyratio', $tschema));

	if ($transaction['amount'] < 1)
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid . ' amount ' .
			$transaction['amount'] . ' is lower than 1. (failed)', ['schema' => $tschema]);
		return 'FAILED';
	}

	if (($transaction['amount'] + $touser['saldo']) > $touser['maxlimit'])
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' amount ' . $transaction['amount'] . ' failed. ' .
			link_user($touser, $tschema, false) . ' over maxlimit.', ['schema' => $tschema]);
		return 'FAILED';
	}

	unset($transaction['letscode_to']);

	if($id = insert_transaction($transaction))
	{
		$app['monolog']->debug('elas-soap: Transaction ' . $transid .
			' processed (success)', ['schema' => $tschema]);
		$transaction['id'] = $id;
		mail_transaction($transaction);

		return 'SUCCESS';
	}

	$app['monolog']->debug('elas-soap: Transaction ' . $transid .
		' failed', ['schema' => $tschema]);

	return 'FAILED';
}

function userbyletscode($apikey, $letscode)
{
	global $app;

	$tschema = $app['this_group']->get_schema();

	$app['monolog']->debug('Lookup request for ' .
		$letscode, ['schema' => $tschema]);

	if ($app['config']->get('maintenance', $tschema))
	{
		return 'OFFLINE';
	}

	if(!check_apikey($apikey,'interlets'))
	{
		$app['monolog']->debug('Apikey fail, apikey: ' . $apikey .
			' (lookup request for letscode ' .
			$letscode . ')', ['schema' => $tschema]);

		return '---';
	}

	$user = get_user_by_letscode($letscode);

	if ($user['status'] != 1 && $user['status'] != 2)
	{
		$app['monolog']->debug('User not active (lookup request for letscode ' .
			$letscode . ')', ['schema' => $tschema]);
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

	$tschema = $app['this_group']->get_schema();

	$app['monolog']->debug('Lookup request for user ' .
		$name, ['schema' => $tschema]);

	if ($app['config']->get('maintenance', $tschema))
	{
		return 'OFFLINE';
	}

	if(!check_apikey($apikey, 'interlets'))
	{
		$app['monolog']->debug('Apikey fail, apikey: ' . $apikey .
			' (lookup request for name ' .
			$name . ')', ['schema' => $tschema]);
		return '---';
	}

	$letscode = $app['db']->fetchColumn('select letscode
		from ' . $tschema . '.users
		where status in (1, 2)
			and name ilike ?', ['%' . $name . '%']);
	return $letscode ?? 'Onbekend';
}

function getstatus($apikey)
{
	global $app;

	$tschema = $app['this_group']->get_schema();

	if ($app['config']->get('maintenance', $tschema))
	{
		return 'OFFLINE';
	}

	if (check_apikey($apikey, 'interlets'))
	{
		return 'OK - eLAND';
	}

	$app['monolog']->debug('Apikey fail, apikey: ' . $apikey .
		' (lookup request for status)', ['schema' => $tschema]);

	return 'APIKEYFAIL';
}

function apiversion($apikey)
{
	global $app;

	$tschema = $app['this_group']->get_schema();

	if ($app['config']->get('maintenance', $tschema))
	{
		return 'OFFLINE';
	}

	if(check_apikey($apikey, 'interlets'))
	{
		return 1200; //soapversion;
	}

	$app['monolog']->debug('Apikey fail, apikey: ' . $apikey .
		' (lookup request for apiversion)', ['schema' => $tschema]);

	return 'APIKEYFAIL';
}

function check_apikey($apikey, $type)
{
	global $app;

	$tschema = $app['this_group']->get_schema();

	return $app['db']->fetchColumn('select apikey
		from ' . $tschema . '.apikeys
		where apikey = ?
		and type = ?', [trim($apikey), trim($type)]) ? true : false;
}

function get_user_by_letscode(string $letscode)
{
	global $app;

	$tschema = $app['this_group']->get_schema();

	$letscode = trim($letscode);
	[$letscode] = explode(' ', $letscode);

	return $app['db']->fetchAssoc('select *
		from ' . $tschema . '.users
		where letscode = ?', [$letscode]);
}
