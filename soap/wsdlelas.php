<?php
$rootpath='../';
$page_access = 'anonymous';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_transactions.php';

$server = new soap_server();
$server->configureWSDL('interletswsdl', 'urn:interletswsdl');

// Register the method to expose
$server->register('gettoken',                // method name
    ['apikey' => 'xsd:string'],        // input parameters
    ['return' => 'xsd:string'],      // output parameters
    'urn:interletswsdl',                      // namespace
    'urn:interletswsdl#gettoken',                // soapaction
    'rpc',                                // style
    'encoded',                            // use
    'Get a login token'            // documentation
);

$server->register('userbyletscode',                // method name
    ['apikey' => 'xsd:string', 'letscode' => 'xsd:string'],        // input parameters
    ['return' => 'xsd:string'],      // output parameters
    'urn:interletswsdl',                      // namespace
    'urn:interletswsdl#userbyletscode',                // soapaction
    'rpc',                                // style
    'encoded',                            // use
    'Get the user'            // documentation
);

$server->register('userbyname',                // method name
    ['apikey' => 'xsd:string', 'name' => 'xsd:string', 'hash' => 'xsd:string'],        // input parameters
    ['return' => 'xsd:string'],      // output parameters
    'urn:interletswsdl',                      // namespace
    'urn:interletswsdl#userbyletscode',                // soapaction
    'rpc',                                // style
    'encoded',                            // use
    'Get the user'            // documentation
);

$server->register('getstatus',                // method name
   ['apikey' => 'xsd:string'],
   ['return' => 'xsd:string'],
   'urn:interletswsdl',                      // namespace
   'urn:interletswsdl#getstatus',
   'rpc',                                // style
   'encoded',                            // use
   'Get the eLAS status'
);

$server->register('apiversion',                // method name
   ['apikey' => 'xsd:string'],
   ['return' => 'xsd:string'],
   'urn:interletswsdl',                      // namespace
   'urn:interletswsdl#apiversion',
   'rpc',                                // style
   'encoded',                            // use
   'Get the eLAS SOAP API version'
);

$server->register('dopayment',
   ['apikey' => 'xsd:string', 'from' => 'xsd:string', 'real_from' => 'xsd:string', 'to' => 'xsd:string', 'description' => 'xsd:string', 'amount' => 'xsd:float', 'transid' => 'xsd:string', 'signature' => 'xsd:string'],
   ['return' => 'xsd:string'],
   'urn:interletswsdl',                      // namespace
   'urn:interletswsdl#dopayment',
   'rpc',                                // style
   'encoded',                            // use
   'Commit an interlets transaction'
);

function gettoken($apikey)
{
	global $db, $schema, $redis;

	log_event('debug', 'Token request');

	if(check_apikey($apikey, 'interlets'))
	{
		$token = 'elasv2' . substr(md5(microtime() . $schema), 0, 12);
		$key = $schema . '_token_' . $token;
		$redis->set($key, '1');
		$redis->expire($key, 600);

		log_event('soap' ,'Token ' . $token . ' generated');
		return $token;
	}

	log_event('soap','apikey fail, apikey: ' . $apikey . ' no token generated');
	return '---';

}

function dopayment($apikey, $from, $real_from, $to, $description, $amount, $transid, $signature)
{
	global $db;

	// Possible status values are SUCCESS, FAILED, DUPLICATE and OFFLINE
	log_event('debug', 'Transaction request from: ' . $from . ' real from: ' . $real_from . ' to: ' . $to . ' description: "' . $description . '" amount: ' . $amount . ' transid: ' . $transid);

	if ($db->fetchColumn('SELECT * FROM transactions WHERE transid = ?', [$transid]))
	{
		log_event('soap', 'Transaction ' . $transid . ' is a duplicate');
		return 'DUPLICATE';
	}

	if (check_apikey($apikey, 'interlets'))
	{
		if(readconfigfromdb('maintenance'))
		{
			log_event('soap', 'Transaction ' . $transid . ' deferred (offline)');
			return 'OFFLINE';
		}
		else
		{
			log_event('debug', 'Looking up Interlets user ' . $from);

			if ($fromuser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', [$from]))
			{
				log_event('debug', 'Found Interlets fromuser ' . json_encode($fromuser));
			}
			else
			{
				log_event('debug', 'NOT found interlets fromuser ' . $from . ' transid: ' . $transid);
			}

			if ($touser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', [$to]))
			{
				log_event('debug', 'Found Interlets touser ' . json_encode($touser));
			}
			else
			{
				log_event('debug', 'Not found Interlets touser ' . $to . ' transid: ' . $transid);
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
				log_event('soap','Transaction ' . $transid . ', unknown FROM user (to:' . $to . ')');
				return 'NOUSER';
			}

			if (empty($touser['letscode']) || ($touser['status'] != 1 && $touser['status'] != 2))
			{
				log_event('soap','Transaction ' . $transid . ', unknown or invalid TO user');
				return 'NOUSER';
			}

			if (empty($transid))
			{
				log_event('soap', 'Transaction ' . $transid . ' missing trans id (failed).');
				return 'FAILED';
			}

			if (empty($description))
			{
				log_event('soap', 'Transaction ' . $transid . ' missing description (failed).');
				return 'FAILED';
			}

			$sigtest = sign_transaction($transaction, $fromuser['presharedkey']);

			if ($sigtest != $signature)
			{
				log_event('soap', 'Transaction ' . $transid . ', invalid signature');
				return 'SIGFAIL';
			}

			$transaction['amount'] = round($amount * readconfigfromdb('currencyratio'));

			if ($transaction['amount'] < 1)
			{
				log_event('soap', 'Transaction ' . $transid . ' amount ' . $transaction['amount'] . ' is lower than 1. (failed)');
				return 'FAILED';
			}

			unset($transaction['letscode_to']);

			if($id = insert_transaction($transaction))
			{
				log_event('soap', 'Transaction ' . $transid . ' processed (success)');
				$transaction['id'] = $id;
				mail_transaction($transaction);

				return 'SUCCESS';
			}

			log_event('soap', 'Transaction ' . $transid . ' failed');

			return 'FAILED';
		}
	}
	else
	{
		log_event('soap','APIKEY failed for Transaction ' . $transid . ' apikey: ' . $apikey);
		return 'APIKEYFAIL';
	}
}

function userbyletscode($apikey, $letscode)
{
	global $db;

	log_event('debug', 'Lookup request for ' . $letscode);
	if(check_apikey($apikey,'interlets'))
	{
		$user = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', [$letscode]);
		if($user['name'] == '')
		{
			return 'Onbekend';
		}
		else
		{
			return $user['name'];
		}
	}
	else
	{
		log_event('debug', 'Apikey fail, apikey: ' . $apikey . ' (lookup request for letscode ' . $letscode . ')');
		return '---';
	}
}

function userbyname($apikey, $name)
{
	global $db;

	log_event('debug', 'Lookup request for user ' . $name);

	if(check_apikey($apikey, 'interlets'))
	{
		$user = $db->fetchAssoc('select * from users where name ilike ?', ['%' . $name . '%']);
		return ($user['name']) ? $user['letscode'] : 'Onbekend';
	}
	else
	{
		log_event('debug', 'Apikey fail, apikey: ' . $apikey . ' (lookup request for name ' . $name . ')');
		return '---';
	}
}

function getstatus($apikey)
{
	//global $elasversion;

	if (check_apikey($apikey, 'interlets'))
	{
		return (readconfigfromdb('maintenance')) ? 'OFFLINE' : 'OK - eLAND';
	}
	else
	{
		log_event('debug', 'Apikey fail, apikey: ' . $apikey . ' (lookup request for status)');
		return 'APIKEYFAIL';
	}
}

function apiversion($apikey)
{
	if(check_apikey($apikey, 'interlets'))
	{
		//global $soapversion;
		return 1200; //$soapversion;
	}
	else
	{
		log_event('debug', 'Apikey fail, apikey: ' . $apikey . ' (lookup request for apiversion)');
	}
}

function check_apikey($apikey, $type)
{
	global $db;

	return ($db->fetchColumn('select apikey
		from apikeys
		where apikey = ?
		and type = ?', [$apikey, $type])) ? true : false;
}

$post_data = file_get_contents('php://input');
$server->service($post_data);
