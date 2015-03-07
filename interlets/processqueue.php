<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_dbconfig.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_transactions.php");

session_start();

// PUT MAIN BODY HERE
echo "Running eLAS Interlets System\n\n";

// Process the interlets Queue
// Foreach entry, try to execute it, than remove if complete OR expired, leave on failures

global $db;

$query = "SELECT * FROM interletsq";
$transactions = $db->GetArray($query);
//         transid VARCHAR( 80 ) NOT NULL,
//        date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
//        id_from INT( 11 ) NOT NULL,
//       letsgroup_id INT( 11 ) NOT NULL,
//        letscode_to VARCHAR ( 20 ) NOT NULL,
//       amount INT( 11 ) NOT NULL,
//        description varchar(60) NOT NULL,
//        signature VARCHAR ( 80 ) NOT NULL,
//        retry_until TIMESTAMP,

$systemtag = readconfigfromdb("systemtag");

foreach ($transactions AS $key => $value){
		$transid = $value['transid'];
		$letsgroup_id = $value['letsgroup_id'];
		$id_from = $value['id_from'];
		$letscode_to = $value['letscode_to'];
		$amount = $value['amount'];
		$description = $value['description'];
		$signature = $value['signature'];
		$retry_until = $value['retry_until'];
		$count = $value['retry_count'];

                echo "Processing transaction $transid\t";

		// Lookup the letsgroup details from letsgroups
		$myletsgroup = get_letsgroup($letsgroup_id);

		$myuser = get_user($value['id_from']);
		$real_from = $myuser["fullname"] ."(" .$myuser["letscode"] .")";

		// Make the SOAP connection, send our API key and the transaction details
		$mysoapurl = $myletsgroup["elassoapurl"] ."/wsdlelas.php?wsdl";
		$myapikey = $myletsgroup["remoteapikey"];
		$from = $myletsgroup["myremoteletscode"];
		$client = new nusoap_client($mysoapurl, true);
		$err = $client->getError();
		if (!$err) {
			$result = $client->call('dopayment', array(
				'apikey' => $myapikey,
				'from' => $from,
				'real_from' => $real_from,
				'to' => $letscode_to,
				'description' => $description,
				'amount' => $amount,
				'transid' => $transid,
				'signature' => $signature
			));
			$err = $client->getError();
    			if (!$err) {
				//return $result;
				// Process the result statusa
				echo $result;
				echo "\n";
				switch ($result){
					case "SUCCESS":
						//Commit locally
						if(localcommit($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to) == "FAILED"){
                                                        update_queue($transid,$count,"LOCALFAIL");
                                                }
						break;
					case "OFFLINE":
						//Do nothing
						update_queue($transid,$count,$result);
						log_event("", "Soap", "Remote eLAS offline $transid");
						break;
					case "FAILED":
						//Handle error and remove transaction
						mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result,1);
						unqueue($transid);
						break;
					case "SIGFAIL":
						//Handle the error and remove transaction
						mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result,1);
						unqueue($transid);
						break;
					case "DUPLICATE":
						//Commit locally
                        if(localcommit($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to) == "FAILED"){
							update_queue($transid,$count,"LOCALFAIL");
						}
						break;
					case "NOUSER":
						//Handle the error and remove transaction
						mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result, 0);
                                                unqueue($transid);
						break;
					case "APIKEYFAIL":
						update_queue($transid,$count,$result);
						break;
					default:
						//Evaluate the date and pop the transaction if needed, handling the error.
						echo "Default handling";
						update_queue($transid,$count,"DEFAULT");
				}
			} else {
				if(strtotime($value["retry_until"]) < time()){
					echo "EXPIRED";
					echo "\n";
				}
				update_queue($transid,$count,"UNKNOWN");
			}
		}

}

echo "\nDone processing.\n\n";

////////////////// FUNCTIONS ///////////////////////////

function unqueue($transid){
	global $db;
	$query = "DELETE FROM interletsq WHERE transid = '" .$transid ."'";
	log_event("","Trans","Removing $transid from queue");
	$db->Execute($query);
}

function update_queue($transid,$count,$result){
	global $db;
	$count = $count + 1;
        $query = "UPDATE interletsq SET retry_count = $count, last_status = '" .$result ."' WHERE transid = '" .$transid ."'";
	$db->Execute($query);
}

function localcommit($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to){
	//FIXME Add data validation and clear error message for bug #321
	//FIXME output debug info when elasdebug = 1
	echo "Local commiting $transid\t\t";
	$ratio = readconfigfromdb("currencyratio");
	$posted_list["amount"] = $amount * $ratio;
	$posted_list["description"] = $description;
	$posted_list["id_from"] = $id_from;
	//Lookup id_to first
	$to_user = get_user_by_letscode($myletsgroup["localletscode"]);

	$posted_list["id_to"] = $to_user["id"];
	//Real_to has to be set by a soap call
	$mysoapurl = $myletsgroup["elassoapurl"] ."/wsdlelas.php?wsdl";
	$myapikey = $myletsgroup["remoteapikey"];
	$client = new nusoap_client($mysoapurl, true);
	$result = $client->call('userbyletscode', array('apikey' => $myapikey, 'letscode' => $letscode_to));
	$err = $client->getError();
	if (!$err) {
		$posted_list["real_to"] = $result;
	}

	$posted_list["transid"] = $transid;
	$posted_list["date"] = date("Y-m-d H:i:s");

	// Validate like a normal transaction
	$error_list = validate_interletsq($posted_list);
	if(!empty($error_list)){
		echo "\nVALIDATION ERRORS\n";
		var_dump($error_list);
		echo "\Tried to commit:\n";
		var_dump($posted_list);
		echo "\n";
	} else {
		$mytransid = insert_transaction($posted_list, $transid);
	}

	if($mytransid == $transid){
		$result = "SUCCESS";
		log_event("","Trans","Local commit of interlets transaction succeeded");
		$posted_list["amount"] = round($posted_list["amount"]);
		mail_transaction($posted_list, $mytransid);
		unqueue($transid);
	} else {
		$result = "FAILED";
		log_event("","Trans","Local commit of $transid failed");
		//FIXME Replace with something less spammy (1 mail per 15 minutes);
		$systemtag = readconfigfromdb("systemtag");
                $mailsubject .= "[eLAS-".$systemtag."] " . "Interlets FAILURE!";
                $from = readconfigfromdb("from_address_transactions");
                $to = readconfigfromdb("admin");

                $mailcontent = "WARNING: LOCAL COMMIT OF TRANSACTION $transid FAILED!!!  This means the transaction is not balanced now!";

                sendemail($from,$to,$mailsubject,$mailcontent);
	}
	echo $result;
	echo "\n";
	return $result;
}

?>
