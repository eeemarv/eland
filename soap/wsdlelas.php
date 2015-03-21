<?php
$rootpath="../";
$role = 'anonymous';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_transactions.php");
require_once($rootpath."includes/inc_apikeys.php");
require_once($rootpath."includes/inc_tokens.php");

// Create the server instance
$server = new soap_server();
// Initialize WSDL support
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

function gettoken($apikey){
	log_event("","debug","Token request");
	if(check_apikey($apikey,"interlets") == 1){
		$token = generate_token("guestlogin");
		log_event("","Soap","Token $token generated");
	} else {
		$token = "---";
		log_event("","Soap","APIkey rejected, no token generated");
	}
	return $token;
}

function dopayment($apikey, $from, $real_from, $to, $description, $amount, $transid, $signature){
	// Possible status values are SUCCESS, FAILED, DUPLICATE and OFFLINE
	log_event("","debug","Transaction request");
	if(check_duplicate_transaction($transid) == 1) {
		log_event("","Soap","Transaction $transid is a duplicate");
		return "DUPLICATE";
	}

        if(check_apikey($apikey, "interlets") == 1){
		if(readconfigfromdb("maintenance") == 1){
			log_event("", "Soap", "Transaction $transid deferred (offline)");
			return "OFFLINE";
		} else {
			$posted_list["transid"] = $transid;
			$posted_list["date"] = date("Y-m-d H:i:s");
			$posted_list["description"] = $description;

			$fromuser = get_user_by_letscode($from);
			$mylog = "Looking up Interlets user $from";
			log_event("","debug", "$mylog");

			$sfrom = serialize($fromuser);
			$mylog = "Found Interlets fromuser $sfrom";
			log_event("","debug", "$mylog");

			$posted_list["id_from"] = $fromuser["id"];
			$posted_list["real_from"] = $real_from;
			$touser = get_user_by_letscode($to);
			$posted_list["id_to"] = $touser["id"];
			$posted_list["amount"] = $amount;
			$posted_list["letscode_to"] = $touser["letscode"];

			if(empty($fromuser["letscode"]) || $fromuser["accountrole"] != 'interlets') {
				log_event("","Soap","Transaction $transid, unknown FROM user");
                                return "NOUSER";
			}

			// Stop already if the user doesn't exist
			if(empty($touser["letscode"]) || ($touser["status"] != 1 && $touser["status"] != 2)) {
				log_event("","Soap","Transaction $transid, unknown or invalid TO user");
                                return "NOUSER";
			}

			// Check the signature first
			$sigtest = sign_transaction($posted_list,$fromuser["presharedkey"]);
			if($sigtest != $signature){
				log_event("","Soap","Transaction $transid, invalid signature");
				return "SIGFAIL";
			}

			$posted_list["amount"] = $amount * readconfigfromdb("currencyratio");

			$mytransid = insert_transaction($posted_list, $transid);
			if($mytransid == $transid){
				$result = "SUCCESS";
				log_event("","Soap","Transaction $transid processed");
				$posted_list["amount"] = round($posted_list["amount"]);
				mail_transaction($posted_list, $transid);
			} else {
				log_event("","Soap","Transaction $transid FAILED");
				$result = "FAILED";
			}
			return $result;
		}
	} else {
		return "APIKEYFAIL";
		log_event("","Soap","APIKEY failed for Transaction $transid");
	}
}

function userbyletscode($apikey, $letscode){
	log_event("","debug","Lookup request for $letscode");
	if(check_apikey($apikey,"interlets") == 1){
		$user = get_user_by_letscode($letscode);
		if($user["fullname"] == ""){
			return "Onbekend";
		} else {
			return $user["fullname"];
		}
	} else {
		return "---";
	}
}

function userbyname($apikey, $name){
        log_event("","debug","Lookup request for user $name");
        if(check_apikey($apikey,"interlets") == 1){
                $user = get_user_by_name($name);
                if($user["fullname"] == ""){
                        return "Onbekend";
                } else {
                        return $user["letscode"];
                }
        } else {
                return "---";
        }
}

function getstatus($apikey){
	global $elasversion;
	if(check_apikey($apikey,"interlets") == 1){
		if(readconfigfromdb("maintenance") == 1){
			return "OFFLINE";
		} else {
			return "OK - eLAS $elasversion";
		}
	} else {
		return "APIKEYFAIL";
	}
}

function apiversion($apikey){
	if(check_apikey($apikey,"interlets") == 1){
		global $soapversion;
		return $soapversion;
	}
}

function messagesearch($term){
        global $db;
        $query = "SELECT *, ";
        $query .= " messages.id AS msgid, ";
        $query .= " users.id AS userid, ";
        $query .= " categories.id AS catid, ";
        $query .= " categories.fullname AS catname, ";
        $query .= " users.name AS username, ";
        $query .= " users.letscode AS letscode, ";
        $query .= " messages.validity AS valdate, ";
        $query .= " messages.cdate AS date ";
        $query .= " FROM messages, users, categories ";
        $query .= "  WHERE messages.id_user = users.id ";
        $query .= " AND messages.id_category = categories.id";
	$query .= " AND messages.content LIKE '%$term%'";
	$messages = $db->GetArray($query);
	return $messages;
}

function messagedetails($msgid){
	global $db;
        $query = "SELECT * FROM messages WHERE id=$msgid";
	$message = $db->GetRow($query);
	$currency = readconfigfromdb("currency");
	$currencyratio = readconfigfromdb("currencyratio");
	$return = $message['id_user'] ."," .$message['content'] ."," .$message['Description'] ."," .$message['amount'] ."," .$currency ."," .$currencyratio ."," .$message['units'] ."," .$message['validity'];
	return $return;
}

// Use the request to (try to) invoke the service
$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
$server->service($HTTP_RAW_POST_DATA);
?>
