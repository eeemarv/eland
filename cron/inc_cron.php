<?php
// Functions required by the cron script

function get_warn_messages($daysnotice)
{
	global $db;
	$now = time();
	$testdate = $now + ($daysnotice * 60 * 60 * 24);
	$testdate = date('Y-m-d', $testdate);
	$query = "SELECT * FROM messages WHERE exp_user_warn = 'f' AND validity < '" .$testdate ."'";
	echo $query;
	$warn_messages  = $db->GetArray($query);
	return $warn_messages;
}

function get_expired_messages()
{
	global $db;
	$now = time();
	$testdate = date('Y-m-d', $now);
	$query = "SELECT * FROM messages WHERE exp_user_warn = 't' AND validity < '" .$testdate ."'";
	echo $query;
	$expired_messages  = $db->GetArray($query);
	return $expired_messages;
}

function do_auto_cleanup_messages()
{
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

function do_auto_cleanup_inactive_messages()
{
	global $db;
	$query = "SELECT * FROM users WHERE status = 0";
	$users = $db->GetArray($query);
	
	foreach ($users AS $key => $value){
		$q2 = "DELETE FROM messages WHERE id_user = " .$value["id"];
		$db->Execute($q2);
	}
}

function mark_expwarn($messageid, $value)
{
	global $db;
	$value = ($value) ? 't' : 'f';
	$query = "UPDATE messages set exp_user_warn = '" .$value ."' WHERE id = " .$messageid;
	$db->Execute($query);
}

function mail_user_expwarn($mailaddr,$subject,$content)
{
	$from_address_transactions = readconfigfromdb("from_address_transactions");
	if (!empty($from_address_transactions))
	{
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

function check_timestamp($lastrun, $agelimit)
{
	// agelimit is the time after which to rerun the job in MINUTES
	return ((time() - ($agelimit * 60)) > strtotime($lastrun)) ? true : false;
}

function write_timestamp($cronjob, $lastrun_ary = null)
{
	global $db;
	if (!isset($lastrun_ary))
	{
		$query = "SELECT lastrun FROM cron WHERE cronjob = '" .$cronjob ."'";
		$job = $db->GetOne($query);
	}
	else
	{
		$job = $lastrun_ary[$cronjob];
	}

	$ts = gmdate("Y-m-d H:i:s");

	if(!$job)
	{
		$q = "INSERT INTO cron(cronjob, lastrun) VALUES ('" .$cronjob ."', '" . $ts . "')";
	}
	else
	{
		$q = "UPDATE cron SET lastrun = '" .$ts ."' WHERE cronjob = '" .$cronjob ."'";
	}
	$db->Execute($q);
	log_event(" ", "Cron", "Cronjob $cronjob finished");
}
