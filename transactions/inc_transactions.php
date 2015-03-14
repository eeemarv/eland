<?php

// Enable logging
$rootpath = "../";

require_once $rootpath . 'includes/inc_mailfunctions.php';


function mail_transaction($posted_list, $timestamp){
	global $configuration, $s_id;

	if (!empty(readconfigfromdb("from_address_transactions"))){
		$mailfrom .= "From: ".trim(readconfigfromdb("from_address_transactions"))."\r\n";
	}else { error_log("No from_address_transaction");return 0;}

	$userfrom=get_user_maildetails($posted_list["id_from"]);
	if (!empty($userfrom["emailaddress"])){
		$mailto .= ",".$userfrom["emailaddress"];
	}

	$userto=get_user_maildetails($posted_list["id_to"]);
	if (!empty($userto["emailaddress"])){
		$mailto .= ",". $userto["emailaddress"];
	}

	$mailsubject .= "[eLAS-".readconfigfromdb("systemtag")."] " . $posted_list["amount"] . " " .readconfigfromdb("currency");
	$mailsubject .= " van " . $userfrom["name"] . " (".trim($posted_list["letscode_from"]).")";
	$mailsubject .= " aan " . $userto["name"] . " (".trim($posted_list["letscode_to"]).")";
	$mailcontent  = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
	$mailcontent .= "Datum: $timestamp\r\n";
	$mailcontent .= "Van: ". $userfrom["name"]. " (".trim($posted_list["letscode_from"]).")\r\n";
	$mailcontent .= "Aan: ". $userto["name"]. " (".trim($posted_list["letscode_to"]).")\r\n";
	$mailcontent .= "Voor: ".$posted_list["description"]."\r\n";
	$mailcontent .= "Aantal: ".$posted_list["amount"]."\r\n";

	sendemail($mailfrom, $mailto, $mailsubject, $mailcontent);
	//mail($mailto,$mailsubject,$mailcontent,$mailfrom);

}

function insert_transaction($posted_list){
        global $db, $s_id;

        if(empty($s_id)){
                $posted_list["creator"] = 0;
        } else {
                $posted_list["creator"] = $s_id;
        }
        
        $posted_list["cdate"] = date("Y-m-d H:i:s");
        if($db->AutoExecute("transactions", $posted_list, 'INSERT') == TRUE){
                setstatus("Transactie toegevoegd", 0);
        } else {
                log_event("","Trans", "Transaction failed");
                setstatus("Fout: Transactie niet toegevoegd", 1);
        }

}


function get_user_maildetails($userid){
	global $db;
	$user = readuser($userid);
	$query = "SELECT * FROM contact, type_contact WHERE id_user = $userid AND id_type_contact = type_contact.id and type_contact.abbrev = 'mail'";
	$contacts = $db->GetRow($query);
	$user["emailaddress"] = $contacts["value"];

	return $user;

}
