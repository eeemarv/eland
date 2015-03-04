<?php
// Functions required by the cron script

function update_stat_msgs($cat_id){
	global $db;
        //$query = "SELECT COUNT(*) AS stat_msg_wanted FROM messages WHERE id_category = ".$cat_id ;
        //$query .= " AND msg_type = 0 ";
	$query = "SELECT COUNT(*) AS stat_msg_wanted";
        $query .= " FROM messages, users ";
        $query .= " WHERE ";
	$query .= " id_category = ".$cat_id ;
        $query .= " AND messages.id_user = users.id ";
        $query .= " AND (users.status = 1 OR users.status = 2 OR users.status = 3) ";
	$query .= " AND msg_type = 0 ";

    	$row = $db->GetRow($query);
        $stat_wanted = $row["stat_msg_wanted"];

        //$query = "SELECT COUNT(*) AS stat_msg_offer FROM messages WHERE id_category = ".$cat_id ;
        //$query .= " AND msg_type = 1 ";
	$query = "SELECT COUNT(*) AS stat_msg_offer";
        $query .= " FROM messages, users ";
        $query .= " WHERE ";
	$query .= " id_category = ".$cat_id ;
        $query .= " AND messages.id_user = users.id ";
        $query .= " AND (users.status = 1 OR users.status = 2 OR users.status = 3) ";
        $query .= " AND msg_type = 1 ";
        $row = $db->GetRow($query);
        $stat_offer = $row["stat_msg_offer"];

        $posted_list["stat_msgs_wanted"] = $stat_wanted;
        $posted_list["stat_msgs_offers"] = $stat_offer;
        $result = $db->AutoExecute("categories", $posted_list, 'UPDATE', "id=$cat_id");
}

function get_cat(){
        global $db;
        $query = "SELECT * FROM categories WHERE leafnote=1 order by fullname";
        $cat_list = $db->GetArray($query);
        return $cat_list;
}

function get_warn_messages($daysnotice) {
	global $db;
	$now = time();
	$testdate = $now + ($daysnotice * 60 * 60 * 24);
	$testdate = date('Y-m-d', $testdate);
	$query = "SELECT * FROM messages WHERE exp_user_warn = 0 AND validity < '" .$testdate ."'";
	echo $query;
	$warn_messages  = $db->GetArray($query);
	return $warn_messages;
}

function do_clear_msgflags(){
        global $db;
	$now = time();
	$daysnotice =  readconfigfromdb("msgexpwarningdays");
	$testdate = $now + ($daysnotice * 60 * 60 * 24);
        $testdate = date('Y-m-d', $testdate);
	$query = "UPDATE messages SET exp_user_warn = 0 WHERE validity > '" .$testdate ."'";
	$db->Execute($query);
}

function get_expired_messages() {
	global $db;
	$now = time();
	$testdate = date('Y-m-d', $now);
	$query = "SELECT * FROM messages WHERE exp_user_warn = 1 AND validity < '" .$testdate ."'";
	echo $query;
	$expired_messages  = $db->GetArray($query);
	return $expired_messages;
}

function do_auto_cleanup_messages(){
        global $db;
        $now = time();
        $daysnotice =  readconfigfromdb("msgexpcleanupdays");
        $testdate = $now - ($daysnotice * 60 * 60 * 24);
        $testdate = date('Y-m-d', $testdate);
        $query = "SELECT * FROM messages WHERE validity < '" .$testdate ."'";
        $messages = $db->GetArray($query);

        foreach ($messages AS $key => $value){
		$mid = $value["id"];
		log_event("","Cron","Expired MessageID $mid deleted");
                $query = "DELETE FROM messages WHERE id = " .$mid;
                $db->Execute($query);
        }
}

function do_auto_cleanup_inactive_messages(){
	global $db;
	$query = "SELECT * FROM users WHERE status = 0";
	$users = $db->GetArray($query);

	foreach ($users AS $key => $value){
		$q2 = "DELETE FROM messages WHERE id_user = " .$value["id"];
		$db->Execute($q2);
	}
}

function do_cleanup_news() {
    global $db;
    $now = date('Y-m-d', time());
	$query = "DELETE FROM news WHERE itemdate < '" .$now ."' AND sticky <> 1";
	$db->Execute($query);
}

function do_cleanup_tokens(){
	global $db;
        $now = date('Y-m-d H:i:s', time());
	$query = "DELETE FROM tokens WHERE validity < '" .$now ."'";
        $db->Execute($query);
}

function mail_admin_expmsg($messages) {
	$admin = readconfigfromdb("admin");
        if (empty($admin)){
	   echo "No admin E-mail address specified in config\n";
	   return 0;
	} else {
	   $mailto = $admin;
	}

	$from_address_transactions = readconfigfromdb("from_address_transactions");
        if (!empty($from_address_transactions)){
                $mailfrom .= "From: ".trim($from_address_transactions)."\r\n";
        }else {
                echo "Mail from address is not set in configuration\n";
                return 0;
        }

	$systemtag = readconfigfromdb("systemtag");
	$mailsubject = "[eLAS-".$systemtag ."] - Rapport vervallen V/A";

	$mailcontent = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n\n";

	$mailcontent .= "ID\tUser\tMessage\n";
	foreach($messages as $key => $value) {
		$mailcontent .=  $value["mid"] ."\t" .$value["username"] ."\t" .$value["message"] ."\t" .$value["validity"] ."\n";
        }

	$mailcontent .=  "\n\n";

	sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
}

function mail_balance($mailaddr,$balance){
	$from_address_transactions = readconfigfromdb("from_address_transactions");
        if (!empty($from_address_transactions)){
                $mailfrom .= trim($from_address_transactions);
        }else {
		echo "Mail from address is not set in configuration\n";
		return 0;
	}

	$mailto = $mailaddr;

	$systemtag = readconfigfromdb("systemtag");
        $mailsubject .= "[eLAS-".$systemtag ."] - Saldo mail";

        $mailcontent .= "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
	$mailcontent .= "\nJe ontvangt deze mail omdat je de optie 'Mail saldo' in eLAS hebt geactiveerd,\nzet deze uit om deze mails niet meer te ontvangen.\n";

	$currency = readconfigfromdb("currency");
	$mailcontent .= "\nJe huidig LETS saldo is " .$balance ." " .$currency ."\n";

	$mailcontent .= "\nDe eLAS MailSaldo Robot\n";
        sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
}

function mark_expwarn($messageid,$value){
	global $db;
	$query = "UPDATE messages set exp_user_warn = '" .$value ."' WHERE id = " .$messageid;
	$db->Execute($query);
}

function mail_user_expwarn($mailaddr,$subject,$content) {
	$from_address_transactions = readconfigfromdb("from_address_transactions");
        if (!empty($from_address_transactions)){
                $mailfrom .= trim($from_address_transactions);
        }else {
                echo "Mail from address is not set in configuration\n";
                return 0;
        }

        $mailto = $mailaddr;

	$systemtag = readconfigfromdb("systemtag");
        $mailsubject .= "[eLAS-".$systemtag ."] - " .$subject;

        $mailcontent .= "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n\n";
        $mailcontent .= "$content\n\n";

	$mailcontent .= "Als je nog vragen of problemen hebt, kan je terecht op ";
        $mailcontent .= readconfigfromdb("support");

        $mailcontent .= "\n\nDe eLAS Robot\n";
        sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);
	log_event("","Mail","Message expiration mail sent to $mailto");
}

function check_timestamp($cronjob,$agelimit){
        // agelimit is the time after which to rerun the job in MINUTES
        global $db;
        $query = "SELECT lastrun FROM cron WHERE cronjob = '" .$cronjob ."'";
        $job = $db->GetRow($query);
        $now = time();
        $limit = $now - ($agelimit * 60);
        $timestamp = strtotime($job["lastrun"]);

        if($limit > $timestamp) {
                return 1;
        } else {
                return 0;
        }

	//log the cronjob execution

}

function write_timestamp($cronjob){
        global $db;
        $query = "SELECT cronjob FROM cron WHERE cronjob = '" .$cronjob ."'";
        $job = $db->GetRow($query);
        $ts = date("Y-m-d H:i:s");

        if($job["cronjob"] != $cronjob){
                $qins = "INSERT INTO cron(cronjob) VALUES ('" .$cronjob ."')";
                $db->execute($qins);
        } else {
                $qupd = "UPDATE cron SET lastrun = '" .$ts ."' WHERE cronjob = '" .$cronjob ."'";
                $db->Execute($qupd);
        }

	//Write completion to eventlog
	log_event(" ","Cron","Cronjob $cronjob finished");
}

?>
