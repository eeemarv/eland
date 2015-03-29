<?php
/**
 * Class to perform eLAS Mail operations
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the  * GNU General Public License for more details.
*/
/** Provided functions:
 * sendemail($mailfrom,$mailto,$mailsubject,$mailcontent)	Immediately send out an e-mail
*/
//require_once($rootpath."contrib/includes/SwiftMail/lib/swift_required.php"); -> autoload.
//require_once($rootpath."includes/inc_userinfo.php");

/*
function message_list_announce($uuid) {
	# FIXME Replace this with direct publishing to AMQ
	global $db;
	global $baseurl;
	global $configuration;

	$query = "SELECT * FROM messages WHERE uuid = '" .$uuid ."'";
	$message = $db->GetRow($query);

	$user = get_user_maildetails($message["id_user"]);

	$listquery = "SELECT * FROM lists WHERE topic = 'messages'";
	$lists = $db->GetArray($listquery);

	foreach($lists as $key => $value){
		//var_dump($value);
		//exit(1);
		$msgid = uniqid();
		//var_dump($value);
		if(empty($user["emailaddress"])){
			$from = "noreply@" . $baseurl;
		} else {
			$from = $user["emailaddress"];
		}

		if($message["msg_type"] == 1) {
			$subject = "Nieuw aanbod: ";
		} else {
			$subject = "Nieuwe vraag: ";
		}

		$subject .= $message["content"];
		$subject = htmlspecialchars($subject, ENT_QUOTES);

		$msg = "Beste LETSers\n\n" .$user["fullname"] . " heeft zonet een nieuw vraag/aanbod ingegeven in eLAS:\n\n";
		$msg .= $message["Description"];
		$msg .= "\n\nDit aanbod is geldig tot: " .$message["validity"] ."\n";
		$msg .= "De vraagprijs is " .$message["amount"] ." " . readconfigfromdb("currency") ." per " .$message["units"] ."\n";
		$directurl="http://" .$baseurl ."/login.php?redirectmsg=" .$message["id"];
		$msg .= "\nBekijk het volledige zoekertje en eventuele foto's op " .$directurl ."\n";

		$msg = htmlspecialchars($msg, ENT_QUOTES);

		if(readconfigfromdb("mailinglists_enabled") == 1){
			$query = "INSERT INTO mailq (msgid, listname, from, subject, message, sent) VALUES ('" .$msgid . "', '" .$value["listname"] ."', '" .$from ."', '" .$subject ."', '" .$msg ."', 0)";
			$dbstatus = $db->Execute($query);
			if($dbstatus == 1) {
				setstatus("V/A wordt verstuurd naar mailinglists",1);
				log_event("", "mail", "V/A [" . $subject ."] wordt verstuurd naar mailinglists");
			} else {
				setstatus("Fout: V/A  kan niet verstuurd worden naar mailinglists", 1);
				log_event("", "mail", "Fout V/A [" . $subject ."] wordt NIET verstuurd naar mailinglists");
			}
		} else {
				setstatus("Mailing lists zijn niet actief",1);
		}
	}
}
*/

function sendemail($mailfrom, $mailto, $mailsubject, $mailcontent){
	global $elasversion;

	if (!readconfigfromdb('mailenabled'))
	{
		log_event("", "mail", "Mail $mailsubject not sent, mail functions are disabled");
		return "Mail functies zijn uitgeschakeld";
	}

	if(empty($mailfrom) || empty($mailto) || empty($mailsubject) || empty($mailcontent))
	{
		$logline = "Mail $mailsubject not sent, missing fields\n";
		$logline .= "From: $mailfrom\nTo: $mailto\nSubject: $mailsubject\nContent: $mailcontent";
		log_event("", "mail", $logline);
		return "Fout: mail niet verstuurd, ontbrekende velden";
	}

	//Filter off leading and trailing commas to avoid errors
	$mailto = preg_replace('/^,/i', '', $mailto);
	$mailto = preg_replace('/,$/i', '', $mailto);

	$toarray = explode(",", $mailto);

	// use the official mandrill api wrapper.
	return sendemail_mandrill_simple($mailfrom, $toarray, $mailsubject, $mailcontent);

	// Swiftmailer below disabled.

	// return 0 on success, 1 on failure
	// use Mandrill for transport
	$transport = Swift_SmtpTransport::newInstance('smtp.mandrillapp.com', 587);
	$transport->setUsername(getenv('MANDRILL_USERNAME'));
	$transport->setPassword(getenv('MANDRILL_PASSWORD'));
	$mailer = Swift_Mailer::newInstance($transport);

	if(readconfigfromdb('mailenabled')){
		if(empty($mailfrom) || empty($mailto) || empty($mailsubject) || empty($mailcontent)){
			$mailstatus = "Fout: mail niet verstuurd, ontbrekende velden";
			$logline = "Mail $mailsubject not sent, missing fields\n";
			$logline .= "From: $mailfrom\nTo: $mailto\nSubject: $mailsubject\nContent: $mailcontent";
			log_event("", "mail", $logline);
		} else {
			$message = Swift_Message::newInstance();
			$message->setSubject($mailsubject);

			try
			{
				$message->setFrom($mailfrom);
			}
			catch (Exception $e)
			{
				$emess = $e->getMessage();
				$mailstatus = "Fout: mail naar $mailto niet verstuurd.";
				log_event("", "mail", "Mail $mailsubject not send, mail command said $emess");
				$status = 0;
			}

			try
			{
				$message->setTo($toarray);
			}
			catch (Exception $e)
			{
				$emess = $e->getMessage();
				$mailstatus = "Fout: mail naar $mailto niet verstuurd.";
				log_event("", "mail", "Mail $mailsubject not send, mail command said $emess");
				$status = 0;
			}

			try
			{
				$message->setBody($mailcontent);
			}
			catch (Exception $e) {
				$emess = $e->getMessage();
				$mailstatus = "Fout: mail naar $mailto niet verstuurd.";
				log_event("", "mail", "Mail $mailsubject not send, mail command said $emess");
				$status = 0;
			}
			$status = 1;
			try
			{
				$mailer->send($message);
			}
			catch (Exception $e) {
				$emess = $e->getMessage();
				$mailstatus = "Fout: mail naar $mailto niet verstuurd.";
				log_event("", "mail", "Mail $mailsubject not send, mail command said $emess");
				$status = 0;
			}
			if($status == 1) {
				$mailstatus = "OK - Mail verstuurd";
				log_event("", "mail", "Mail $mailsubject sent to $mailto");
			}
		}
	} else {
		$mailstatus = "Mail functies zijn uitgeschakeld";
		log_event("", "mail", "Mail $mailsubject not sent, mail functions are disabled");
	}

	return $mailstatus;
}



function sendemail_mandrill_simple($from, $to, $subject, $content)
{
	global $s_id;

	$to_mandrill = array_map(function($email_address){return array('email' => $email_address);}, $to);

	try {
		$mandrill = new Mandrill(); 

		$message = array(
			'subject' => $subject,
			'text' => $content,
			'from_email' => $from,
			'to' => $to_mandrill,
		);
		
		$mandrill->messages->send($message, true);

		$to = (is_array($to)) ? implode(', ', $to) : $to;

		log_event($s_id, 'mail', 'mail sent, subject: ' . $subject . ', from: ' . $from . ', to: ' . $to);
	}
	catch (Mandrill_Error $e)
	{
		// Mandrill errors are thrown as exceptions
		log_event($s_id, 'mail', 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage());
		
		// throw $e;
		return 'Mail niet verzonden. Fout in mail service.';
	}

	return false;
}
