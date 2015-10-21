<?php
$rootpath='../';
$role = 'anonymous';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_transactions.php';

$server = new soap_server();
$server->configureWSDL('interletswsdl', 'urn:interletswsdl');

// Register the method to expose
$server->register('gettoken',                // method name
    array('apikey' => 'xsd:string'),        // input parameters
    array('return' => 'xsd:string'),      // output parameters
    'urn:interletswsdl',                      // namespace
    'urn:interletswsdl#gettoken',                // soapaction
    'rpc',                                // style
    'encoded',                            // use
    'Get a login token'            // documentation
);

$server->register('userbyletscode',                // method name
    array('apikey' => 'xsd:string', 'letscode' => 'xsd:string'),        // input parameters
    array('return' => 'xsd:string'),      // output parameters
    'urn:interletswsdl',                      // namespace
    'urn:interletswsdl#userbyletscode',                // soapaction
    'rpc',                                // style
    'encoded',                            // use
    'Get the user'            // documentation
);

$server->register('userbylogin',                // method name
    array('apikey' => 'xsd:string', 'login' => 'xsd:string', 'hash' => 'xsd:string'),        // input parameters
    array('return' => 'xsd:string'),      // output parameters
    'urn:interletswsdl',                      // namespace
    'urn:interletswsdl#userbyletscode',                // soapaction
    'rpc',                                // style
    'encoded',                            // use
    'Get the user'            // documentation
);

$server->register('userbyname',                // method name
    array('apikey' => 'xsd:string', 'name' => 'xsd:string', 'hash' => 'xsd:string'),        // input parameters
    array('return' => 'xsd:string'),      // output parameters
    'urn:interletswsdl',                      // namespace
    'urn:interletswsdl#userbyletscode',                // soapaction
    'rpc',                                // style
    'encoded',                            // use
    'Get the user'            // documentation
);

$server->register('getstatus',                // method name
   array('apikey' => 'xsd:string'),
   array('return' => 'xsd:string'),
   'urn:interletswsdl',                      // namespace
   'urn:interletswsdl#getstatus',
   'rpc',                                // style
   'encoded',                            // use
   'Get the eLAS status'
);

$server->register('apiversion',                // method name
   array('apikey' => 'xsd:string'),
   array('return' => 'xsd:string'),
   'urn:interletswsdl',                      // namespace
   'urn:interletswsdl#apiversion',
   'rpc',                                // style
   'encoded',                            // use
   'Get the eLAS SOAP API version'
);

$server->register('dopayment',
   array('apikey' => 'xsd:string', 'from' => 'xsd:string', 'real_from' => 'xsd:string', 'to' => 'xsd:string', 'description' => 'xsd:string', 'amount' => 'xsd:float', 'transid' => 'xsd:string', 'signature' => 'xsd:string'),
   array('return' => 'xsd:string'),
   'urn:interletswsdl',                      // namespace
   'urn:interletswsdl#dopayment',
   'rpc',                                // style
   'encoded',                            // use
   'Commit an interlets transaction'
);

function gettoken($apikey)
{
	global $db, $schema;
	log_event('', 'debug', 'Token request');
	if(check_apikey($apikey, 'interlets'))
	{
		$token = array(
			'token'		=> md5(microtime() . $schema),
			'validity'	=> date('Y-m-d H:i:s', time() + (10 * 60)),
			'type'		=> 'guestlogin'
		);

		$db->insert('tokens', $token);

		log_event('' ,'Soap' ,'Token ' . $token['token'] . ' generated');
	}
	else
	{
		$token = '---';
		log_event('','Soap','APIkey rejected, no token generated');
	}
	return $token['token'];
}

function dopayment($apikey, $from, $real_from, $to, $description, $amount, $transid, $signature)
{
	// Possible status values are SUCCESS, FAILED, DUPLICATE and OFFLINE
	log_event('','debug','Transaction request');
	
	if (check_duplicate_transaction($transid))
	{
		log_event('','Soap','Transaction ' . $transid . ' is a duplicate');
		return 'DUPLICATE';
	}

	if (check_apikey($apikey, 'interlets'))
	{
		if(readconfigfromdb('maintenance'))
		{
			log_event('', 'Soap', 'Transaction ' . $transid . ' deferred (offline)');
			return 'OFFLINE';
		}
		else
		{
			$fromuser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($from));
			log_event('','debug', 'Looking up Interlets user ' . $from);
			log_event('','debug', 'Found Interlets fromuser ' . serialize($fromuser));

			$touser = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($to));

			$transaction = array(
				'transid'		=> $transid,
				'date' 			=> date('Y-m-d H:i:s'),
				'description' 	=> $description,
				'id_from' 		=> $fromuser['id'],
				'real_from' 	=> $real_from,
				'id_to' 		=> $touser['id'],
				'amount' 		=> $amount,
				'letscode_to' 	=> $touser['letscode'],
			);

			if (empty($fromuser['letscode']) || $fromuser['accountrole'] != 'interlets')
			{
				log_event('','Soap','Transaction ' . $transid . ', unknown FROM user');
				return 'NOUSER';
			}

			if (empty($touser['letscode']) || ($touser['status'] != 1 && $touser['status'] != 2))
			{
				log_event('','Soap','Transaction ' . $transid . ', unknown or invalid TO user');
				return 'NOUSER';
			}

			$sigtest = sign_transaction($transaction, $fromuser['presharedkey']);

			if ($sigtest != $signature)
			{
				log_event('','Soap','Transaction ' . $transid . ', invalid signature');
				return 'SIGFAIL';
			}

			$transaction['amount'] = $amount * readconfigfromdb('currencyratio');

			if(insert_transaction($transaction))
			{
				$result = 'SUCCESS';
				log_event('','Soap','Transaction ' . $transid . ' processed');
				$transaction['amount'] = round($transaction['amount']);
				mail_transaction($transaction, $transid);
			}
			else
			{
				log_event('','Soap','Transaction ' . $transid . ' FAILED');
				$result = 'FAILED';
			}
			return $result;
		}
	}
	else
	{
		return 'APIKEYFAIL';
		log_event('','Soap','APIKEY failed for Transaction ' . $transid);
	}
}

function userbyletscode($apikey, $letscode)
{
	log_event('','debug','Lookup request for ' . $letscode);
	if(check_apikey($apikey,'interlets'))
	{
		$user = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($letscode));
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
		return '---';
	}
}

function userbyname($apikey, $name)
{
	log_event('', 'debug', 'Lookup request for user ' . $name);

	if(check_apikey($apikey,'interlets'))
	{
		$user = $db->fetchAssoc('SELECT * FROM users WHERE (LOWER(name)) LIKE \'%?%\'', array(strtolower($name)));
		return ($user['name']) ? $user['letscode'] : 'Onbekend';
	}
	else
	{
		return '---';
	}
}

function getstatus($apikey)
{
	//global $elasversion;

	if (check_apikey($apikey, 'interlets'))
	{
		return (readconfigfromdb('maintenance')) ? 'OFFLINE' : 'OK - eLAS-Heroku'; //  . $elasversion;
	}
	else
	{
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
}

function check_apikey($apikey, $type)
{
	global $db;

	return ($db->fetchColumn('select apikey
		from apikeys
		where apikey = ?
		and type = ?', array($apikey, $type))) ? true : false;
}

// Use the request to (try to) invoke the service
$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
$server->service($HTTP_RAW_POST_DATA);
