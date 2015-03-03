<?php

// Enable logging
$rootpath = "../";

function mail_transaction($posted_list, $timestamp){
	global $configuration;
	session_start();
	$s_id = $_SESSION["id"];


// no mail for demo site or when it not configured
	if (readconfigfromdb("mailenabled") !== "1" ){
	    error_log("Mail is not enabled");
	return 0;
	}

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

	mail($mailto,$mailsubject,$mailcontent,$mailfrom);
	// log it
//	echo "mail";
}

function insert_transaction($posted_list){
        global $db;
        global $_SESSION;
        $s_id = $_SESSION["id"];
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

function mail_deleted_transaction($transaction,$reason){
        global $configuration;
        session_start();
        $s_id = $_SESSION["id"];


	# Who did it
	$currentuser = get_user_maildetails($s_id);

	// no mail for demo site or when it not configured
        if (readconfigfromdb("mailenabled") !== "1" ){
            error_log("Mail is not enabled");
        return 0;
        }

        if (!empty(readconfigfromdb("from_address_transactions"))){
                $mailfrom .= "From: ".trim(readconfigfromdb("from_address_transactions"))."\r\n";
        }else { error_log("No from_address_transaction");return 0;}

        $userfrom=get_user_maildetails($transaction["id_from"]);
        if (!empty($userfrom["emailaddress"])){
                $mailto .= ",".$userfrom["emailaddress"];
        }

        $userto=get_user_maildetails($transaction["id_to"]);
        if (!empty($userto["emailaddress"])){
                $mailto .= ",". $userto["emailaddress"];
        }

	# Include the administrator in the to field
	if (!empty(readconfigfromdb("admin"))){
                $mailto .= ",". readconfigfromdb("admin");
	}

        $mailsubject .= "[eLAS-".readconfigfromdb("systemtag")."] " . "Transactie verwijderd";
        $mailcontent  = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
	$mailcontent .= "Onderstaande transactie werd door " .$currentuser["name"] ."(" .$currentuser["emailaddress"] .") verwijderd om deze reden:\r\n";
	$mailcontent .= $reason ."\r\n";
        $mailcontent .= "Datum: ". $transaction["date"] ."\r\n";
        $mailcontent .= "Van: ". $userfrom["name"] ."\r\n";
        $mailcontent .= "Aan: ". $userto["name"] ."\r\n";
        $mailcontent .= "Voor: ".$transaction["description"]."\r\n";
        $mailcontent .= "Aantal: ".$transaction["amount"]."\r\n";

        mail($mailto,$mailsubject,$mailcontent,$mailfrom);
	//echo "$mailto $mailsubject $mailcontent $mailfrom";
        // log it
        log_event($s_id,"Mail","Transaction deletion sent to $mailto");

}

function get_user_maildetails($userid){
	global $db;
	$query = "SELECT * FROM users WHERE id = $userid";
	$user = $db->GetRow($query);
	$query = "SELECT * FROM contact, type_contact WHERE id_user = $userid AND id_type_contact = type_contact.id and type_contact.abbrev = 'mail'";
	$contacts = $db->GetRow($query);
	$user["emailaddress"] = $contacts["value"];
	

	return $user;

}

?>
