<?php
/**
 * Class to perform eLAS transactions
 *
 * This file is part of eLAS http://elas.vsbnet.be
 *
 * Copyright(C) 2009 Guy Van Sanden <guy@vsbnet.be>
 *
 * eLAS is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

/** Provided functions:
 * insert_transaction($posted_list, $s_id)
 * get_transaction_by_id($transid)
 * validate_transaction_input($posted_list)
 * mail_transaction($posted_list, $transid)
 * generate_transid()
*/

// Enable logging
global $rootpath;
require_once($rootpath."includes/inc_saldofunctions.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_mailfunctions.php");
require_once($rootpath."includes/inc_amq.php");

function generate_transid(){
	global $baseurl;
	global $s_id;
	$genid = sha1($s_id .microtime()) .$_SESSION["id"] ."@" . $baseurl;
	return $genid;
}

function sign_transaction($posted_list, $sharedsecret) {
	$signamount = (float) $posted_list["amount"];
	$signamount = $signamount * 100;
	$signamount = round($signamount);
	$tosign = $sharedsecret .$posted_list["transid"] .strtolower($posted_list["letscode_to"]) .$signamount;
	$signature = sha1($tosign);
	log_event("","debug","Signing $tosign: $signature");
	return $signature;
}

function check_duplicate_transaction($transid){
	global $db;
	$query = "SELECT * FROM transactions WHERE transid = '" .$transid ."'";
	$result = $db->GetArray($query);
	if(count($result) > 0){
		return 1;
	} else {
		return 0;
	}
}

function insert_transaction($posted_list, $transid){
    global $db, $s_id;

	if(empty($s_id)){
		$posted_list["creator"] = 0;
	} else {
	        $posted_list["creator"] = $s_id;
	}
    $posted_list["cdate"] = date("Y-m-d H:i:s");
	$posted_list["transid"] = $transid;
	if($db->AutoExecute("transactions", $posted_list, 'INSERT') == TRUE){
		setstatus("Transactie toegevoegd", 0);
		log_event("","Trans", "Transaction $transid saved");
		//Update the balances
		update_saldo($posted_list["id_from"]);
		update_saldo($posted_list["id_to"]);
	} else {
		$reason = $db->ErrorMsg();
		log_event("","Trans", "Transaction $transid failed with error $reason");
		setstatus("Fout: Transactie niet toegevoegd", 1);
		$transid = "";
	}

	return $transid;
}

function get_transaction_by_id($transid){
	global $db;
	$query = "SELECT * FROM transactions WHERE transid = '" .$transid ."'";
	$transaction = $db->GetRow($query);
        return $transaction;

}

function validate_inteletstransaction_input($posted_list){
        global $db;
        global $_SESSION;
		$s_accountrole = $_SESSION["accountrole"];
        $error_list = array();

        //description may not be empty
        if (!isset($posted_list["description"])|| (trim($posted_list["description"] )=="")){
        $error_list["description"]="Dienst is niet ingevuld";
        }

        //amount may not be empty
        $var = trim($posted_list["amount"]);
        if (!isset($posted_list["amount"])|| (trim($posted_list["amount"] )=="")){
                $error_list["amount"]="Bedrag is niet ingevuld";
        } elseif ($posted_list['amount'] == '0'){
			$error_list['amount'] = 'Bedrag kan niet nul zijn.';
		} elseif (!ctype_digit($posted_list['amount'])){
                $error_list["amount"]="Bedrag is geen geldig getal";
        }

        //Amount may not be over the limit
        $user = get_user($posted_list["id_from"]);
        if (($user["saldo"] - $posted_list["amount"]) < $user["minlimit"] && $_SESSION["accountrole"] != "admin"){
                $error_list["amount"]="Je beschikbaar saldo laat deze transactie niet toe";
        }

        //userfrom must exist
        $query = "SELECT * from  users ";
        $query .= " WHERE id = '".$posted_list["id_from"]."' " ;
        $query .= " AND users.status <> '0'" ;
        $rs = $db->Execute($query);
        $number2 = $rs->recordcount();
        if( $number2 == 0 ){
                $error_list["id_from"]="Gebruiker bestaat niet";
        }

       //date may not be empty
        if (!isset($posted_list["date"])|| (trim($posted_list["date"] )=="")){
                $error_list["date"]="Datum is niet ingevuld";
        }elseif(strtotime($posted_list["date"]) == -1){
                $error_list["date"]="Fout in datumformaat (jjjj-mm-dd)";
        }

	//Description may not exceed 60 characters.
	if(strlen($posted_list["description"]) > 60){
		$error_list["description"] = "Omschrijving is te lang (max 60 karakters)";
	}

        return $error_list;
}

function validate_transaction_input($posted_list){
	global $db;
	global $_SESSION;
	$s_accountrole = $_SESSION["accountrole"];
        $error_list = array();

        //description may not be empty
        if (!isset($posted_list["description"])|| (trim($posted_list["description"] )=="")){
        $error_list["description"]="Dienst is niet ingevuld";
        }

        //amount may not be empty
        $var = trim($posted_list["amount"]);
        if (!isset($posted_list["amount"])|| (trim($posted_list["amount"] )=="")){
                $error_list["amount"]="Bedrag is niet ingevuld";
        //amount amy only contain  numbers between 0 en 9
        }elseif(eregi('^[0-9]+$', $var) == FALSE){
                $error_list["amount"]="Bedrag is geen geldig getal";
        }

	//Amount may not be over the limit
	$user = get_user($posted_list["id_from"]);
	if(($user["saldo"] - $posted_list["amount"]) < $user["minlimit"] && $_SESSION["accountrole"] != "admin"){
		$error_list["amount"]="Je beschikbaar saldo laat deze transactie niet toe";
	}

        //userfrom must exist
	$fromuser = get_user($posted_list["id_from"]);
        if(empty($fromuser)){
                $error_list["id_from"]="Gebruiker bestaat niet";
        }

        //userto must exist
	$touser = get_user($posted_list["id_to"]);
        if(empty($touser) ){
                $error_list["id_to"]="Gebruiker bestaat niet";
	}

	//userfrom and userto should not be the same
	if($fromuser["letscode"] == $touser["letscode"]){
		$error_list["id"]="Van en Aan zijn hetzelfde";
	}

	// Check maxlimit now we are at it
	if(($touser["maxlimit"] != NULL && $touser["maxlimit"] != 0) && $touser["saldo"] > $touser["maxlimit"] && $s_accountrole != "admin"){
		$error_list["id_to"]="De bestemmeling heeft zijn maximum limiet bereikt";
	}

	// Double check if the recipient is an active user
	if(!($touser["status"] == 1 || $touser["status"] == 2)) {
		$error_list["id_to"]="De bestemmeling is niet actief";
	}

        //From and to may not be identical
        //if ($posted_list["id_from"] == $posted_list["id_to"]){
	//	$error_list["id_from"]="Van en aan moeten vershillend zijn";
        //}

        //date may not be empty
        if (!isset($posted_list["date"])|| (trim($posted_list["date"] )=="")){
                $error_list["date"]="Datum is niet ingevuld";
        }elseif(strtotime($posted_list["date"]) == -1){
                $error_list["date"]="Fout in datumformaat (jjjj-mm-dd)";
        }

        return $error_list;
}

function validate_interletsq($posted_list){
	global $db;
	global $_SESSION;
	$s_accountrole = $_SESSION["accountrole"];
        $error_list = array();

        //description may not be empty
        if (!isset($posted_list["description"])|| (trim($posted_list["description"] )=="")){
        $error_list["description"]="Dienst is niet ingevuld";
        }
/*
		if (!ctype_digit($posted_list['amount'])
		{
			$error_list['amount'] = 'Bedrag moet uit cijfers bestaan';
		}
*/
        //amount may not be empty
        $var = trim($posted_list["amount"]);
        if (!isset($posted_list["amount"]) || (trim($posted_list["amount"] ) == "" || !$posted_list['amount'])){
                $error_list["amount"]="Bedrag is niet ingevuld";
        }

        //userfrom must exist
	$fromuser = get_user($posted_list["id_from"]);
        if(empty($fromuser)){
                $error_list["id_from"]="Gebruiker bestaat niet";
        }

        //userto must exist
	$touser = get_user($posted_list["id_to"]);
        if(empty($touser) ){
                $error_list["id_to"]="Gebruiker bestaat niet";
	}

	//userfrom and userto should not be the same
	if($fromuser["letscode"] == $touser["letscode"]){
		$error_list["id"]="Van en Aan zijn hetzelfde";
	}

        //date may not be empty
        if (!isset($posted_list["date"])|| (trim($posted_list["date"] )=="")){
                $error_list["date"]="Datum is niet ingevuld";
        }elseif(strtotime($posted_list["date"]) == -1){
                $error_list["date"]="Fout in datumformaat (jjjj-mm-dd)";
        }

        return $error_list;
}

function mail_interlets_transaction($posted_list, $transid){
        session_start();
        $s_id = $_SESSION["id"];

        $mailfrom = readconfigfromdb("from_address_transactions");

        $userfrom=get_user_maildetails($posted_list["id_from"]);
        //$mailto .= ",".$userfrom["emailaddress"];

        //$userto=get_user_maildetails($posted_list["id_to"]);
        //$mailto .= ",". $userto["emailaddress"];
	$userto = get_user_mailaddresses($posted_list["id_to"]);
	$mailto .= ",". $userto;

	$systemname = readconfigfromdb("systemname");
        $systemtag = readconfigfromdb("systemtag");
        $currency = readconfigfromdb("currency");

        $mailsubject .= "[eLAS-".$systemtag."] " . "Interlets transactie";

        $mailcontent  = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";

	$mailcontent  = "Er werd een interlets transactie ingegeven op de eLAS installatie van $systemname met de volgende gegevens:\r\n\r\n";
        //$mailcontent .= "Datum: \t\t$timestamp\r\n"
        if(!empty($posted_list["real_from"])){
                $mailcontent .= "Van: \t\t". $posted_list["real_from"] ."\r\n";
        } else {
                $mailcontent .= "Van: \t\t". $userfrom["fullname"] ."\r\n";
        }

	$mailcontent .= "Aan: \t\t". $posted_list["letscode_to"] ."\r\n";

	$mailcontent .= "Voor: \t\t".$posted_list["description"]."\r\n";
	//calculate metacurrency
	$currencyratio = readconfigfromdb("currencyratio");
	$meta = round($posted_list["amount"] / $currencyratio, 4);

        $mailcontent .= "Aantal: \t".$posted_list["amount"]. " $currency ($meta LETS uren*, $currencyratio $currency = 1 uur)\r\n";
        $mailcontent .= "\r\nTransactieID: \t\t$transid\r\n";

	$mailcontent .= "\r\nJe moet deze in je eigen systeem verder verwerken.\r\n";
	$mailcontent .= "\r\nAls dit niet mogelijk is moet je de kern van de andere groep verwittigen zodat ze de transactie aan hun kant annuleren.\r\n";

        $mailcontent .= "\r\n--\nDe eLAS transactie robot\r\n";

	$mailcontent .= "\r\n\r\n* Wat zijn LETS uren? http://elas.vsbnet.be/content/wat-een-lets-uur-elas-2x\r\n";

        sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
        // log it
        log_event($s_id,"Mail","Transaction sent to $mailto");
}

function mail_transaction($posted_list, $transid){
	session_start();
	$s_id = $_SESSION["id"];

	$mailfrom = readconfigfromdb("from_address_transactions");

	$userfrom=get_user_maildetails($posted_list["id_from"]);
	if($userfrom["accountrole"] != "interlets"){
		$mailto .= ",".$userfrom["emailaddress"];
	}

	$userto=get_user_maildetails($posted_list["id_to"]);
	//if($userto["accountrole"] != "interlets"){
	//	$mailto .= ",". $userto["emailaddress"];
	//}
        $userto_mail = get_user_mailaddresses($posted_list["id_to"]);
        $mailto .= ",". $userto_mail;

	$systemtag = readconfigfromdb("systemtag");
	$currency = readconfigfromdb("currency");

	$mailsubject .= "[eLAS-".$systemtag."] " . $posted_list["amount"] . " " .$currency;
	if(!empty($posted_list["real_from"])){
		$mailsubject .= " van " . $posted_list["real_from"];
	} else {
		$mailsubject .= " van " . $userfrom["fullname"] ;
	}
	if(!empty($posted_list["real_to"])){
		$mailsubject .= " aan " . $posted_list["real_to"];
	} else {
		$mailsubject .= " aan " . $userto["fullname"] ;
	}

	$mailcontent  = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
	//$mailcontent .= "Datum: \t\t$timestamp\r\n";
	if(!empty($posted_list["real_from"])){
		$mailcontent .= "Van: \t\t". $posted_list["real_from"] ."\r\n";
	} else {
		$mailcontent .= "Van: \t\t". $userfrom["fullname"] ."\r\n";
	}
	if(!empty($posted_list["real_to"])){
                $mailcontent .= "Aan: \t\t". $posted_list["real_to"] ."\r\n";
        } else {
		$mailcontent .= "Aan: \t\t". $userto["fullname"] ."\r\n";
	}
	#$mailcontent .= print_r($userto);
	$mailcontent .= "Voor: \t\t".$posted_list["description"]."\r\n";
	$mailcontent .= "Aantal: \t".$posted_list["amount"]."\r\n";
	$mailcontent .= "\r\nTransactieID: \t\t$transid\r\n";

	$mailcontent .= "\r\n--\nDe eLAS transactie robot\r\n";

	sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
	// log it
	log_event($s_id,"Mail","Transaction sent to $mailto");
}

function mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result,$admincc){
	$mailfrom = readconfigfromdb("from_address_transactions");

        $systemtag = readconfigfromdb("systemtag");
        $currency = readconfigfromdb("currency");

	$mailsubject .= "[eLAS-".$systemtag."] Gefaalde transactie $transid" ;

        $userfrom=get_user_maildetails($id_from);
        if($userfrom["accountrole"] != "interlets"){
                $mailto = $userfrom["emailaddress"];
        }

	if($admincc == 1){
                $mailto .= ",". readconfigfromdb("admin");
        }

        //$mailcontent .= "Datum: \t\t$timestamp\r\n";
	$mailcontent  = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
	$mailcontent .= "Je interlets transactie hieronder kon niet worden uitgevoerd om de volgende reden:\r\n";
	$mailcontent .= "\r\n";

	switch ($result){
		case "SIGFAIL":
			$mailcontent .= "De digitale handtekening was ongeldig, dit wijst op een fout in de instellingen van de 2 eLAS installlatie.  Deze melding werd ook naar de site-beheerder verstuurd.\r\n";
			break;
		case "EXPIRED":
			$mailcontent .= "Na 4 dagen kon geen contact met de andere eLAS installatie gemaakt worden, probeer de transactie later opnieuw of verwittig de beheerder als dit blijft.\r\n";
			break;
		case "NOUSER":
			$mailcontent .= "De gebruiker met die letscode bestaat niet in de andere groep.  Controleer de letscode via de interlets-functies en probeer het eventueel opnieuw.\r\n";
                        break;
	}
	$mailcontent .= "\r\n";

	// Transaction details
	$amount = $amount * readconfigfromdb("currencyratio");
	$amount = round($amount);
	$mailcontent .= "--\r\n";
        $mailcontent .= "Letscode: \t". $letscode_to ."\r\n";
        $mailcontent .= "Voor: \t\t".$description."\r\n";
        $mailcontent .= "Aantal: \t".$amount." $currency\r\n";
        $mailcontent .= "\r\nTransactieID: \t\t$transid\r\n";
	$mailcontent .= "--\r\n";

        $mailcontent .= "\r\n--\nDe eLAS transactie robot\r\n";

        sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
        // log it
        log_event($s_id,"Mail","Interlets failure sent to $mailto");
}

function queuetransaction($posted_list,$fromuser,$touser) {
	global $db, $redis, $session_name;

	// Send transaction to ETS if enabled
	/*if(readconfigfromdb("ets_enabled") == 1) {
		amq_publishtransaction($posted_list,$fromuser,$touser);
	}*/

	$posted_list["retry_count"] = 0;
	$posted_list["last_status"] = "NEW";
	if($db->AutoExecute("interletsq", $posted_list, 'INSERT') == TRUE){
                setstatus("Transactie in wachtrij", 0);
		$transid = $posted_list["transid"];
			if (!$redis->get($session_name . '_interletsq'))
			{
				$redis->set($session_name . '_interletsq', time());
			}
        } else {
                setstatus("Fout: Transactie niet opgeslagen", 1);
                $transid = "";
        }

        return $transid;
}

function get_transaction($id){
	global $db;
	$query = "SELECT *, ";
	$query .= " transactions.id AS transactionid, ";
	$query .= " fromusers.id AS userid, ";
	$query .= " fromusers.fullname AS fromusername, tousers.fullname AS tousername, ";
	$query .= " fromusers.letscode AS fromletscode, tousers.letscode AS toletscode, ";
	$query .= " transactions.date AS datum, ";
	$query .= " transactions.cdate AS cdatum ";
	$query .= " FROM transactions, users  AS fromusers, users AS tousers";
	$query .= " WHERE transactions.id_to = tousers.id";
	$query .= " AND transactions.id_from = fromusers.id";
	$query .= " AND transactions.id =".$id;
	$transaction = $db->GetRow($query);
	return $transaction;
}


