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
	return sha1($s_id .microtime()) .$_SESSION['id'] .'@' . $base_url;
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
	return ($db->fetchColumn('SELECT * FROM transactions WHERE transid = ?', array($transid))) ? 1 : 0;
}

function insert_transaction($posted_list)
{
    global $db, $s_id;

	$posted_list['creator'] = (empty($s_id)) ? 0 : $s_id;
    $posted_list['cdate'] = date('Y-m-d H:i:s');

	$db->beginTransaction();
	try
	{
		$db->insert('transactions', $posted_list);
		$db->executeUpdate('update users set saldo = saldo + ? where id = ?', array($posted_list['amount'], $posted_list['id_to']));
		$db->executeUpdate('update users set saldo = saldo - ? where id = ?', array($posted_list['amount'], $posted_list['id_from']));
		$db->commit();

	}
	catch(Exception $e)
	{
		$db->rollback();
		throw $e;
		return false;
	}
	$to_user = readuser($posted_list['id_to'], true);
	$from_user = readuser($posted_list['id_from'], true);

	register_shutdown_function('check_auto_minlimit',
		$to_user['id'], $from_user['id'], $posted_list['amount']);

	log_event($s_id, 'Trans', 'Transaction ' . $posted_list['transid'] . ' saved: ' .
		$posted_list['amount'] . ' from user id ' . $posted_list['id_from'] . ' to user id ' . $posted_list['id_to']);

	return true;

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

	$subject .= '[' . $systemtag . '] Interlets transactie';

	$content  = "-- Dit is een automatische mail, niet beantwoorden aub --\n\r\n\r";

	$content  = 'Er werd een interlets transactie ingegeven op de installatie van ' . $systemname  . " met de volgende gegevens:\r\n\r\n";

	if(!empty($posted_list["real_from"]))
	{
		$content .= "Van: \t\t". $posted_list['real_from'] ."\r\n";
	}
	else
	{
		$content .= "Van: \t\t". $userfrom['fullname'] ."\r\n";
	}

	$content .= "Aan: \t\t". $posted_list['letscode_to'] ."\r\n";

	$content .= "Voor: \t\t".$posted_list['description']."\r\n";

	$currencyratio = readconfigfromdb('currencyratio');
	$meta = round($posted_list["amount"] / $currencyratio, 4);

	$content .= "Aantal: \t".$posted_list["amount"]. " $currency ($meta LETS uren*, $currencyratio $currency = 1 uur)\r\n";
	$content .= "\r\nTransactieID: \t\t" . $posted_list['transid'] . "\r\n";

	$content .= "\r\nJe moet deze in je eigen systeem verder verwerken.\r\n";
	$content .= "\r\nAls dit niet mogelijk is moet je de kern van de andere groep verwittigen zodat ze de transactie aan hun kant annuleren.\r\n";

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
		$to = get_mailaddresses($posted_list['id_from']);
	}

	$userto = readuser($posted_list['id_to']);

	$userto_mail = get_mailaddresses($posted_list['id_to']);

	$to .= ",". $userto_mail;

	$systemtag = readconfigfromdb('systemtag');
	$currency = readconfigfromdb('currency');

	$subject .= '[' . $systemtag . '] ' . $posted_list['amount'] . ' ' .$currency;
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

	$content  = "-- Dit is een automatische mail, niet beantwoorden aub --\r\n\r\n";

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

function queuetransaction($posted_list,$fromuser,$touser)
{
	global $db, $redis, $schema;

	unset($posted_list['date'],$posted_list['id_to']);
	$posted_list["retry_count"] = 0;
	$posted_list["last_status"] = "NEW";
	if ($db->insert("interletsq", $posted_list))
	{
		if (!$redis->get($schema . '_interletsq'))
		{
			$redis->set($schema . '_interletsq', time());
		}
		return $posted_list['transid'];
	}
	return '';
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

function check_auto_minlimit($to_user_id, $from_user_id, $amount)
{
	global $elas_mongo, $db, $s_id;

	if (!$to_user_id || !$from_user_id)
	{
		return;
	}

	$user = readuser($to_user_id);
	$from_user = readuser($from_user_id);

	if (!$user
		|| !$amount
		|| !is_array($user)
		|| !in_array($user['status'], array(1, 2))
		|| !$from_user
		|| !is_array($from_user)
		|| !$from_user['letscode']
	)
	{
		return;
	}

	$elas_mongo->connect();
	$a = $elas_mongo->settings->findOne(array('name' => 'autominlimit'));

	$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

	$user['status'] = ($newusertreshold < strtotime($user['adate'] && $user['status'] == 1)) ? 3 : $user['status'];

	$inclusive = explode(',', $a['inclusive']);
	$exclusive = explode(',', $a['exclusive']);
	$trans_exclusive = explode(',', $a['trans_exclusive']);

	array_walk($inclusive, function(&$val){ return strtolower(trim($val)); });	
	array_walk($exclusive, function(&$val){ return strtolower(trim($val)); });
	array_walk($trans_exclusive, function(&$val){ return strtolower(trim($val)); });

	$inclusive = array_fill_keys($inclusive, true);
	$exclusive = array_fill_keys($exclusive, true);
	$trans_exclusive = array_fill_keys($trans_exclusive, true);

	$inc = $inclusive[strtolower($user['letscode'])] ? true :false; 

	if (!is_array($a)
		|| !$a['enabled']
		|| ($user['status'] == 1 && !$a['active_no_new_or_leaving'] && !$inc)
		|| ($user['status'] == 2 && !$a['leaving'] && !$inc)
		|| ($user['status'] == 3 && !$a['new'] && !$inc) 
		|| (isset($exclusive[trim(strtolower($user['letscode']))]))
		|| (isset($trans_exclusive[trim(strtolower($from_user['letscode']))]))
		|| ($a['min'] >= $user['minlimit'])
		|| ($a['account_base'] >= $user['saldo']) 
	)
	{
		error_log('auto_minlimit: no new minlimit for user ' . link_user($user, null, false) . "\n");
		return;
	}

	$extract = round(($a['trans_percentage'] / 100) * $amount);

	if (!$extract)
	{
		return;
	}

	$new_minlimit = $user['minlimit'] - $extract;
	$new_minlimit = ($new_minlimit < $a['min']) ? $a['min'] : $new_minlimit;

	write_new_limit($to_user_id, $new_minlimit);
	log_event($s_id, 'auto_minlimit', 'new minlimit : ' . $new_minlimit . ' for user ' . $user['letscode'] . ' ' . $user['fullname'] . ' (id:' . $to_user_id . ') ');	
}

function write_new_limit($user_id, $new_limit, $type = 'min')
{
	global $elas_mongo, $db;

	$e = array(
		'user_id'	=> $user_id,
		'limit'		=> $new_limit,
		'type'		=> $type,
		'ts'		=> new MongoDate(),
	);

	$elas_mongo->connect();
	$elas_mongo->limit_events->insert($e);
	$db->update('users', array($type . 'limit' => $new_limit), array('id' => $user_id));
	readuser($user_id, true);
}
