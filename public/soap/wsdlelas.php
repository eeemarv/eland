<?php declare(strict_types=1);

require_once __DIR__ . '/../../include/web_legacy.php';

if (!$config_service->get('template_lets', $schema))
{
	echo 'NO_ELAS_TIMEBANK';
	exit;
}

if (!$config_service->get('interlets_en', $schema))
{
	echo 'NO_INTERSYSTEM';
	exit;
}

$server = new \soap_server();
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
	global $app, $schema;

	if ($config_service->get('maintenance', $schema))
	{
		$logger->debug('elas-soap: Transaction token request deferred (offline)',
			['schema' => $schema]);

		return 'OFFLINE';
	}

	$logger->debug('Token request',
		['schema' => $schema]);

	if(check_apikey($apikey, 'interlets'))
	{
		$token = md5(random_bytes(16));

		$key = $schema . '_token_' . $token;

		$predis->set($key, $apikey);
		$predis->expire($key, 600);

		$logger->debug('elas-soap: Token ' . $token .
			' generated', ['schema' => $schema]);

		return 'elasv2' . $token;
	}

	$logger->debug('elas-soap: apikey fail, apikey: ' . $apikey .
		' no token generated', ['schema' => $schema]);

	return '---';
}

function dopayment($apikey, $from, $real_from, $to, $description, $amount, $transid, $signature)
{
	global $app, $schema;

	if ($config_service->get('maintenance', $schema))
	{
		$logger->debug('elas-soap: Transaction ' . $transid .
			' deferred (offline)', ['schema' => $schema]);

		return 'OFFLINE';
	}

	// Possible status values are SUCCESS, FAILED, DUPLICATE and OFFLINE

	$logger->debug('Transaction request from: ' . $from .
		' real from: ' . $real_from . ' to: ' . $to .
		' description: "' . $description . '" amount: ' .
		$amount . ' transid: ' . $transid, ['schema' => $schema]);

	if ($db->fetchColumn('select *
		from ' . $schema . '.transactions
		where transid = ?', [$transid]))
	{
		$logger->debug('elas-soap: Transaction ' . $transid .
			' is a duplicate', ['schema' => $schema]);
		return 'DUPLICATE';
	}

	if (!check_apikey($apikey, 'interlets'))
	{
		$logger->debug('elas-soap: APIKEY failed for Transaction ' . $transid .
			' apikey: ' . $apikey, ['schema' => $schema]);

		return 'APIKEYFAIL';
	}

	$logger->debug('Looking up interSystem user ' .
		$from, ['schema' => $schema]);

	if ($fromuser = get_user_by_letscode($from))
	{
		$logger->debug('Found interSystem fromuser ' .
			json_encode($fromuser), ['schema' => $schema]);
	}
	else
	{
		$logger->debug('NOT found interSystem fromuser ' . $from .
			' transid: ' . $transid, ['schema' => $schema]);
	}

	if ($touser = get_user_by_letscode($to))
	{
		$logger->debug('Found InterSystem touser ' .
			json_encode($touser), ['schema' => $schema]);
	}
	else
	{
		$logger->debug('Not found InterSystem touser ' . $to . ' transid: ' .
			$transid, ['schema' => $schema]);
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
		$logger->debug('elas-soap: Transaction ' . $transid .
			', unknown FROM user (to:' . $to . ')', ['schema' => $schema]);
		return 'NOUSER';
	}

	if (empty($touser['letscode']) || ($touser['status'] != 1 && $touser['status'] != 2))
	{
		$logger->debug('elas-soap: Transaction ' . $transid .
			', unknown or invalid TO user', ['schema' => $schema]);
		return 'NOUSER';
	}

	if (empty($transid))
	{
		$logger->debug('elas-soap: Transaction ' . $transid .
			' missing trans id (failed).', ['schema' => $schema]);
		return 'FAILED';
	}

	if (empty($description))
	{
		$logger->debug('elas-soap: Transaction ' . $transid .
			' missing description (failed).', ['schema' => $schema]);
		return 'FAILED';
	}

	$sigtest = $transaction_service->sign($transaction, $fromuser['presharedkey'], $schema);

	if ($sigtest != $signature)
	{
		$logger->debug('elas-soap: Transaction ' . $transid .
			', invalid signature', ['schema' => $schema]);
		return 'SIGFAIL';
	}

	$transaction['amount'] = round($amount * $config_service->get('currencyratio', $schema));

	if ($transaction['amount'] < 1)
	{
		$logger->debug('elas-soap: Transaction ' . $transid . ' amount ' .
			$transaction['amount'] . ' is lower than 1. (failed)',
			['schema' => $schema]);
		return 'FAILED';
	}

	if (($transaction['amount'] + $touser['saldo']) > $touser['maxlimit'])
	{
		$logger->debug('elas-soap: Transaction ' . $transid .
			' amount ' . $transaction['amount'] . ' failed. ' .
			$account_render->str_id($touser['id'], $schema) .
			' over maxlimit.', ['schema' => $schema]);
		return 'FAILED';
	}

	unset($transaction['letscode_to']);

	if($id = $transaction_service->insert($transaction, $schema))
	{
		$logger->debug('elas-soap: Transaction ' . $transid .
			' processed (success)',
			['schema' => $schema]);
		$transaction['id'] = $id;

		// from eLAS interSystem
		$mail_transaction_service->queue($transaction, $schema);

		return 'SUCCESS';
	}

	$logger->debug('elas-soap: Transaction ' . $transid .
		' failed', ['schema' => $schema]);

	return 'FAILED';
}

function userbyletscode($apikey, $letscode)
{
	global $app, $schema;

	$logger->debug('Lookup request for ' .
		$letscode, ['schema' => $schema]);

	if ($config_service->get('maintenance', $schema))
	{
		return 'OFFLINE';
	}

	if(!check_apikey($apikey,'interlets'))
	{
		$logger->debug('Apikey fail, apikey: ' . $apikey .
			' (lookup request for letscode ' .
			$letscode . ')', ['schema' => $schema]);

		return '---';
	}

	$user = get_user_by_letscode($letscode);

	if ($user['status'] != 1 && $user['status'] != 2)
	{
		$logger->debug('User not active (lookup request for letscode ' .
			$letscode . ')', ['schema' => $schema]);
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
	global $app, $schema;

	$logger->debug('Lookup request for user ' .
		$name, ['schema' => $schema]);

	if ($config_service->get('maintenance', $schema))
	{
		return 'OFFLINE';
	}

	if(!check_apikey($apikey, 'interlets'))
	{
		$logger->debug('Apikey fail, apikey: ' . $apikey .
			' (lookup request for name ' .
			$name . ')', ['schema' => $schema]);
		return '---';
	}

	$letscode = $db->fetchColumn('select letscode
		from ' . $schema . '.users
		where status in (1, 2)
			and name ilike ?', ['%' . $name . '%']);
	return $letscode ?? 'Onbekend';
}

function getstatus($apikey)
{
	global $app, $schema;

	if ($config_service->get('maintenance', $schema))
	{
		return 'OFFLINE';
	}

	if (check_apikey($apikey, 'interlets'))
	{
		return 'OK - eLAND';
	}

	$logger->debug('Apikey fail, apikey: ' . $apikey .
		' (lookup request for status)', ['schema' => $schema]);

	return 'APIKEYFAIL';
}

function apiversion($apikey)
{
	global $app, $schema;

	if ($config_service->get('maintenance', $schema))
	{
		return 'OFFLINE';
	}

	if(check_apikey($apikey, 'interlets'))
	{
		return 1200; //soapversion;
	}

	$logger->debug('Apikey fail, apikey: ' . $apikey .
		' (lookup request for apiversion)',
		['schema' => $schema]);

	return 'APIKEYFAIL';
}

function check_apikey($apikey, $type)
{
	global $app, $schema;

	return $db->fetchColumn('select apikey
		from ' . $schema . '.apikeys
		where apikey = ?
		and type = ?', [trim($apikey), trim($type)]) ? true : false;
}

function get_user_by_letscode(string $letscode)
{
	global $app, $schema;

	$letscode = trim($letscode);
	[$letscode] = explode(' ', $letscode);

	return $db->fetchAssoc('select *
		from ' . $schema . '.users
		where letscode = ?', [$letscode]);
}
