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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

function generate_transid()
{
	global $base_url, $s_id;
	return substr(sha1($s_id .microtime()), 0, 12) . '_' . $s_id . '@' . $base_url;
}

function sign_transaction($transaction, $sharedsecret)
{
	$signamount = (float) $transaction['amount'];
	$signamount = $signamount * 100;
	$signamount = round($signamount);
	$tosign = $sharedsecret . $transaction['transid'] . strtolower($transaction['letscode_to']) . $signamount;
	$signature = sha1($tosign);
	log_event('','debug','Signing ' . $tosign . ' : ' . $signature);
	return $signature;
}

function check_duplicate_transaction($transid)
{
	global $db;
	return ($db->fetchColumn('SELECT * FROM transactions WHERE transid = ?', array($transid))) ? 1 : 0;
}

function insert_transaction($transaction)
{
    global $db, $s_id;

	$transaction['creator'] = (empty($s_id)) ? 0 : $s_id;
    $transaction['cdate'] = date('Y-m-d H:i:s');

	$db->beginTransaction();
	try
	{
		$db->insert('transactions', $transaction);
		$db->executeUpdate('update users set saldo = saldo + ? where id = ?', array($transaction['amount'], $transaction['id_to']));
		$db->executeUpdate('update users set saldo = saldo - ? where id = ?', array($transaction['amount'], $transaction['id_from']));
		$db->commit();

	}
	catch(Exception $e)
	{
		$db->rollback();
		throw $e;
		return false;
	}
	$to_user = readuser($transaction['id_to'], true);
	$from_user = readuser($transaction['id_from'], true);

	autominlimit_queue($transaction['id_from'], $transaction['id_to'], $transaction['amount']);

	log_event($s_id, 'Trans', 'Transaction ' . $transaction['transid'] . ' saved: ' .
		$transaction['amount'] . ' from user id ' . $transaction['id_from'] . ' to user id ' . $transaction['id_to']);

	return true;

}

function mail_interlets_transaction($transaction)
{
	global $s_id;

	$from = readconfigfromdb("from_address_transactions");

	$userfrom = readuser($transaction['id_from']);

	$to = get_mailaddresses($transaction['id_to']);

	$systemname = readconfigfromdb('systemname');
	$systemtag = readconfigfromdb('systemtag');
	$currency = readconfigfromdb('currency');

	$subject .= '[' . $systemtag . '] Interlets transactie';

	$content  = "-- Dit is een automatische mail, niet beantwoorden aub --\n\r\n\r";

	$content  = 'Er werd een interlets transactie ingegeven op de installatie van ' . $systemname  . " met de volgende gegevens:\r\n\r\n";

	if(!empty($transaction["real_from"]))
	{
		$content .= "Van: \t\t". $transaction['real_from'] ."\r\n";
	}
	else
	{
		$content .= "Van: \t\t". $userfrom['fullname'] ."\r\n";
	}

	$content .= "Aan: \t\t". $transaction['letscode_to'] ."\r\n";

	$content .= "Voor: \t\t".$transaction['description']."\r\n";

	$currencyratio = readconfigfromdb('currencyratio');
	$meta = round($transaction["amount"] / $currencyratio, 4);

	$content .= "Aantal: \t".$transaction["amount"]. " $currency ($meta LETS uren*, $currencyratio $currency = 1 uur)\r\n";
	$content .= "\r\nTransactieID: \t\t" . $transaction['transid'] . "\r\n";

	$content .= "\r\nJe moet deze in je eigen systeem verder verwerken.\r\n";
	$content .= "\r\nAls dit niet mogelijk is moet je de kern van de andere groep verwittigen zodat ze de transactie aan hun kant annuleren.\r\n";

	sendemail($from, $to, $subject, $content);
	log_event($s_id, 'Mail', 'Transaction sent to ' . $to);
}

function mail_transaction($transaction)
{
	global $s_id;

	$from = readconfigfromdb("from_address_transactions");

	$userfrom = readuser($transaction['id_from']);
	
	if($userfrom['accountrole'] != 'interlets')
	{
		$to = get_mailaddresses($transaction['id_from']);
	}

	$userto = readuser($transaction['id_to']);

	$userto_mail = get_mailaddresses($transaction['id_to']);

	$to .= ",". $userto_mail;

	$systemtag = readconfigfromdb('systemtag');
	$currency = readconfigfromdb('currency');

	$subject .= '[' . $systemtag . '] ' . $transaction['amount'] . ' ' .$currency;
	if(!empty($transaction["real_from"]))
	{
		$subject .= " van " . $transaction["real_from"];
	} else {
		$subject .= " van " . $userfrom["fullname"] ;
	}
	if(!empty($transaction["real_to"]))
	{
		$subject .= " aan " . $transaction["real_to"];
	} else {
		$subject .= " aan " . $userto["fullname"] ;
	}

	$content  = "-- Dit is een automatische mail, niet beantwoorden aub --\r\n\r\n";

	if(!empty($transaction["real_from"]))
	{
		$content .= "Van: \t\t". $transaction["real_from"] ."\r\n";
	}
	else
	{
		$content .= "Van: \t\t". $userfrom["fullname"] ."\r\n";
	}
	if(!empty($transaction["real_to"]))
	{
		$content .= "Aan: \t\t". $transaction["real_to"] ."\r\n";
    }
    else
    {
		$content .= "Aan: \t\t". $userto["fullname"] ."\r\n";
	}

	$content .= "Voor: \t\t".$transaction["description"]."\r\n";
	$content .= "Aantal: \t".$transaction["amount"]."\r\n";
	$content .= "\r\nTransactieID: \t\t".$transaction['transid'] . "\r\n";

	sendemail($from, $to, $subject, $content);
	log_event($s_id, 'Mail', 'Transaction sent to ' . $to);
}

function mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result,$admincc)
{
	$from = readconfigfromdb("from_address_transactions");

	$systemtag = readconfigfromdb("systemtag");
	$currency = readconfigfromdb("currency");

	$subject .= '['. $systemtag . '] Gefaalde transactie ' . $transid;

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
	$content  = "-- Dit is een automatische mail, niet beantwoorden aub --\r\n";
	$content .= "Je interlets transactie hieronder kon niet worden uitgevoerd om de volgende reden:\r\n";
	$content .= "\r\n";

	switch ($result){
		case "SIGFAIL":
			$content .= "De digitale handtekening was ongeldig, dit wijst op een fout in de instellingen van de installlatie.  Deze melding werd ook naar de site-beheerder verstuurd.\r\n";
			break;
		case "EXPIRED":
			$content .= "Na 4 dagen kon geen contact met de andere installatie gemaakt worden, probeer de transactie later opnieuw of verwittig de beheerder als dit blijft.\r\n";
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

	$content .= "\r\n--\nDe transactie robot\r\n";

	sendemail($from,$to,$subject,$content);
	log_event($s_id, 'Mail', 'Interlets failure sent to ' . $to);
}

function get_mailaddresses($uid)
{
	global $db;

	$addr = array();
	$st = $db->prepare('select c.value
		from contact c, type_contact tc
		where c.id_type_contact = tc.id
			and c.id_user = ?
			and tc.abbrev = \'mail\'');

	$st->bindValue(1, $uid);
	$st->execute();

	while($row = $st->fetch())
	{
		$addr[] = $row['value'];
	}
	return implode(', ', $addr);
}
