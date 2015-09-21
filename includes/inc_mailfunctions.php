<?php
/**
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

function sendemail($from, $to, $subject, $content)
{
	global $s_id;

	if (!readconfigfromdb('mailenabled'))
	{
		log_event('', 'mail', 'Mail ' . $subject . ' not sent, mail functions are disabled');
		return 'Mail functies zijn uitgeschakeld';
	}

	if(empty($from) || empty($to) || empty($subject) || empty($content))
	{
		$log = "Mail $subject not sent, missing fields\n";
		$log .= "From: $from\nTo: $to\nSubject: $subject\nContent: $content";
		log_event("", "mail", $log);
		return 'Fout: mail niet verstuurd, ontbrekende velden';
	}

	$to = trim($to, ',');

	$to = explode(',', $to);

	$to_mandrill = array_map(function($email_address){return array('email' => $email_address);}, $to);

	$message = array(
		'subject'		=> $subject,
		'text'			=> $content,
		'from_email'	=> $from,
		'to'			=> $to_mandrill,
	);

	try {
		$mandrill = new Mandrill(); 
		$mandrill->messages->send($message, true);
	}
	catch (Mandrill_Error $e)
	{
		log_event($s_id, 'mail', 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage());
		return 'Mail niet verzonden. Fout in mail service.';
	}

	$to = (is_array($to)) ? implode(', ', $to) : $to;

	log_event($s_id, 'mail', 'mail sent, subject: ' . $subject . ', from: ' . $from . ', to: ' . $to);

	return false;
}
