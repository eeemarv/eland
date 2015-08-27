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

require_once $rootpath . 'includes/inc_mailfunctions.php';

function generate_transid()
{
	global $baseurl, $s_id;
	return sha1($s_id .microtime()) .$_SESSION['id'] .'@' . $baseurl;
}

function sign_transaction($posted_list, $sharedsecret)
{
	$signamount = (float) $posted_list['amount'];
	$signamount = $signamount * 100;
	$signamount = round($signamount);
	$tosign = $sharedsecret .$posted_list['transid'] .strtolower($posted_list['letscode_to']) .$signamount;
	$signature = sha1($tosign);
	log_event('','debug','Signing ' . $tosign . ' : ' . $signature);
	return $signature;
}

function check_duplicate_transaction($transid)
{
	global $db;
	return ($db->GetOne('SELECT * FROM transactions WHERE transid = \'' .$transid . '\'')) ? 1 : 0;
}

function insert_transaction($posted_list)
{
    global $db, $s_id;

	$posted_list['creator'] = (empty($s_id)) ? 0 : $s_id;
    $posted_list['cdate'] = date('Y-m-d H:i:s');

	$db->StartTrans();
	$db->AutoExecute('transactions', $posted_list, 'INSERT');
	$db->Execute('update users
		set saldo = saldo + ' . $posted_list['amount'] . '
		where id = ' . $posted_list['id_to']);
	$db->Execute('update users
		set saldo = saldo - ' . $posted_list['amount'] . '
		where id = ' . $posted_list['id_from']);
	if ($db->CompleteTrans())
	{
		readuser($posted_list['id_to'], true);
		readuser($posted_list['id_from'], true);

		log_event($s_id, 'Trans', 'Transaction ' . $posted_list['transid'] . ' saved: ' .
			$posted_list['amount'] . ' from user id ' . $posted_list['id_from'] . ' to user id ' . $posted_list['id_to']);

		return true;
	}

	$reason = $db->ErrorMsg();
	log_event($s_id, 'Trans', 'Transaction ' . $transid . ' failed with error ' . $reason);

	return false;
}

function mail_interlets_transaction($posted_list)
{
	global $s_id;

	$from = readconfigfromdb("from_address_transactions");

	$userfrom = readuser($posted_list['id_from']);

	$to = get_mailaddresses($posted_list['id_to']);

	$systemname = readconfigfromdb('systemname');
	$systemtag = readconfigfromdb('systemtag');
	$currency = readconfigfromdb('currency');

	$subject .= "[eLAS-".$systemtag."] " . "Interlets transactie";

	$content  = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";

	$content  = "Er werd een interlets transactie ingegeven op de eLAS installatie van $systemname met de volgende gegevens:\r\n\r\n";

	if(!empty($posted_list["real_from"]))
	{
		$content .= "Van: \t\t". $posted_list["real_from"] ."\r\n";
	}
	else
	{
		$content .= "Van: \t\t". $userfrom["fullname"] ."\r\n";
	}

	$content .= "Aan: \t\t". $posted_list["letscode_to"] ."\r\n";

	$content .= "Voor: \t\t".$posted_list["description"]."\r\n";

	$currencyratio = readconfigfromdb('currencyratio');
	$meta = round($posted_list["amount"] / $currencyratio, 4);

	$content .= "Aantal: \t".$posted_list["amount"]. " $currency ($meta LETS uren*, $currencyratio $currency = 1 uur)\r\n";
	$content .= "\r\nTransactieID: \t\t" . $posted_list['transid'] . "\r\n";

	$content .= "\r\nJe moet deze in je eigen systeem verder verwerken.\r\n";
	$content .= "\r\nAls dit niet mogelijk is moet je de kern van de andere groep verwittigen zodat ze de transactie aan hun kant annuleren.\r\n";

	$content .= "\r\n--\nDe eLAS transactie robot\r\n";

	sendemail($from, $to, $subject, $content);
	log_event($s_id, 'Mail', 'Transaction sent to ' . $to);
}

function mail_transaction($posted_list)
{
	global $s_id;

	$from = readconfigfromdb("from_address_transactions");

	$userfrom = readuser($posted_list['id_from']);
	
	if($userfrom['accountrole'] != 'interlets')
	{
		$to = get_mailaddresses($posted_list['id_to']);
	}

	$userto = readuser($posted_list['id_to']);

	$userto_mail = get_mailaddresses($posted_list['id_to']);

	$to .= ",". $userto_mail;

	$systemtag = readconfigfromdb("systemtag");
	$currency = readconfigfromdb("currency");

	$subject .= "[eLAS-".$systemtag."] " . $posted_list["amount"] . " " .$currency;
	if(!empty($posted_list["real_from"]))
	{
		$subject .= " van " . $posted_list["real_from"];
	} else {
		$subject .= " van " . $userfrom["fullname"] ;
	}
	if(!empty($posted_list["real_to"]))
	{
		$subject .= " aan " . $posted_list["real_to"];
	} else {
		$subject .= " aan " . $userto["fullname"] ;
	}

	$content  = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";

	if(!empty($posted_list["real_from"]))
	{
		$content .= "Van: \t\t". $posted_list["real_from"] ."\r\n";
	}
	else
	{
		$content .= "Van: \t\t". $userfrom["fullname"] ."\r\n";
	}
	if(!empty($posted_list["real_to"]))
	{
		$content .= "Aan: \t\t". $posted_list["real_to"] ."\r\n";
    }
    else
    {
		$content .= "Aan: \t\t". $userto["fullname"] ."\r\n";
	}

	$content .= "Voor: \t\t".$posted_list["description"]."\r\n";
	$content .= "Aantal: \t".$posted_list["amount"]."\r\n";
	$content .= "\r\nTransactieID: \t\t".$posted_list['transid'] . "\r\n";

	$content .= "\r\n--\nDe eLAS transactie robot\r\n";

	sendemail($from, $to, $subject, $content);
	log_event($s_id, 'Mail', 'Transaction sent to ' . $to);
}

function mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result,$admincc)
{
	$from = readconfigfromdb("from_address_transactions");

	$systemtag = readconfigfromdb("systemtag");
	$currency = readconfigfromdb("currency");

	$subject .= "[eLAS-".$systemtag."] Gefaalde transactie $transid" ;

	$userfrom = readuser($id_from);


	if($userfrom["accountrole"] != "interlets")
	{
		$to = get_mailaddresses($id_from);
	}

	if($admincc == 1)
	{
		$to .= ",". readconfigfromdb("admin");
	}

	//$content .= "Datum: \t\t$timestamp\r\n";
	$content  = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
	$content .= "Je interlets transactie hieronder kon niet worden uitgevoerd om de volgende reden:\r\n";
	$content .= "\r\n";

	switch ($result){
		case "SIGFAIL":
			$content .= "De digitale handtekening was ongeldig, dit wijst op een fout in de instellingen van de 2 eLAS installlatie.  Deze melding werd ook naar de site-beheerder verstuurd.\r\n";
			break;
		case "EXPIRED":
			$content .= "Na 4 dagen kon geen contact met de andere eLAS installatie gemaakt worden, probeer de transactie later opnieuw of verwittig de beheerder als dit blijft.\r\n";
			break;
		case "NOUSER":
			$content .= "De gebruiker met die letscode bestaat niet in de andere groep.  Controleer de letscode via de interlets-functies en probeer het eventueel opnieuw.\r\n";
		break;
	}
	$content .= "\r\n";

	// Transaction details
	$amount = $amount * readconfigfromdb("currencyratio");
	$amount = round($amount);
	$content .= "--\r\n";
	$content .= "Letscode: \t". $letscode_to ."\r\n";
	$content .= "Voor: \t\t".$description."\r\n";
	$content .= "Aantal: \t".$amount." $currency\r\n";
	$content .= "\r\nTransactieID: \t\t$transid\r\n";
	$content .= "--\r\n";

	$content .= "\r\n--\nDe eLAS transactie robot\r\n";

	sendemail($from,$to,$subject,$content);
	// log it
	log_event($s_id, 'Mail', 'Interlets failure sent to ' . $to);
}

function queuetransaction($posted_list,$fromuser,$touser)
{
	global $db, $redis, $schema;

	$posted_list["retry_count"] = 0;
	$posted_list["last_status"] = "NEW";
	if ($db->AutoExecute("interletsq", $posted_list, 'INSERT'))
	{
		$transid = $posted_list["transid"];
		if (!$redis->get($schema . '_interletsq'))
		{
			$redis->set($schema . '_interletsq', time());
		}
	}
	else
	{
			$transid = "";
	}

	return $transid;
}

function get_mailaddresses($uid)
{
	global $db;

	$addr = array();
	$rs = $db->Execute('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ' . $uid . '
			and tc.abbrev = \'mail\'');
	while($row = $rs->FetchRow())
	{
		$addr[] = $row['value'];
	}
	return implode(', ', $addr);
}

