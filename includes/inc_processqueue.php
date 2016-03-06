<?php

require_once $rootpath . 'includes/inc_transactions.php';

function processqueue()
{
	global $db, $systemtag;

	echo "Running Interlets System\n\n";

	$transactions = $db->fetchAll('SELECT * FROM interletsq');

	foreach ($transactions AS $key => $value)
	{
		$transid = $value['transid'];
		$letsgroup_id = $value['letsgroup_id'];
		$id_from = $value['id_from'];
		$letscode_to = trim($value['letscode_to']);
		$amount = $value['amount'];
		$description = $value['description'];
		$signature = $value['signature'];
		$retry_until = $value['retry_until'];
		$count = $value['retry_count'];

		echo 'Processing transaction' .  $transid . "\t";

		$myletsgroup = $db->fetchAssoc('select * from letsgroups where id = ?', array($letsgroup_id));

		$myuser = readuser($value['id_from']);
		$real_from = $myuser['letscode'] . ' ' . $myuser['name'];

		$soapurl = ($myletsgroup['elassoapurl']) ?: $myletsgroup['url'] . '/soap';
		$soapurl .= '/wsdlelas.php?wsdl';

		// Make the SOAP connection, send our API key and the transaction details
		$myapikey = $myletsgroup['remoteapikey'];
		$from = $myletsgroup['myremoteletscode'];
		$client = new nusoap_client($soapurl, true);
		$err = $client->getError();
		if (!$err)
		{
			$result = $client->call('dopayment', array(
				'apikey' => $myapikey,
				'from' => $from,
				'real_from' => $real_from,
				'to' => $letscode_to,
				'description' => $description,
				'amount' => $amount,
				'transid' => $transid,
				'signature' => $signature
			));
			$err = $client->getError();
			if (!$err)
			{
				//return $result;
				// Process the result statusa
				echo $result;
				echo "\n";
				switch ($result)
				{
					case 'SUCCESS':
						//Commit locally
						if(localcommit($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to) == 'FAILED')
						{
							update_queue($transid, $count, 'LOCALFAIL');
						}
						break;
					case 'OFFLINE':
						//Do nothing
						update_queue($transid,$count,$result);
						log_event('', 'Soap', 'Remote site offline ' . $transid);
						break;
					case 'FAILED':
						//Handle error and remove transaction
						mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result);
						unqueue($transid);
						break;
					case 'SIGFAIL':
						//Handle the error and remove transaction
						mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result);
						unqueue($transid);
						break;
					case 'DUPLICATE':
						//Commit locally
						if(localcommit($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to) == 'FAILED'){
							update_queue($transid,$count,'LOCALFAIL');
						}
						break;
					case 'NOUSER':
						//Handle the error and remove transaction
						mail_failed_interlets($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to, $result);
						unqueue($transid);
						break;
					case 'APIKEYFAIL':
						update_queue($transid,$count,$result);
						break;
					default:
						//Evaluate the date and pop the transaction if needed, handling the error.
						echo 'Default handling';
						update_queue($transid, $count, 'DEFAULT');
				}
			}
			else
			{
				if(strtotime($value['retry_until']) < time())
				{
					echo 'EXPIRED';
					echo "\n";
				}
				update_queue($transid, $count, 'UNKNOWN');
			}
		}

	}

	echo "\nDone processing.\n\n";
}


function unqueue($transid)
{
	global $db;
	$db->delete('interletsq', array('transid' => $transid));
	log_event('','Trans','Removing ' . $transid . 'from queue');	
}

function update_queue($transid,$count,$result)
{
	global $db;
	$count++;
	$db->update('interletsq', array('retry_count' => $count, 'last_status' => $result), array('transid' => $transid));
}

function localcommit($myletsgroup, $transid, $id_from, $amount, $description, $letscode_to)
{
	global $db, $systemtag;

	//FIXME Add data validation and clear error message for bug #321
	//FIXME output debug info when elasdebug = 1
	echo 'Local commiting ' . $transid . "\t\t";
	$ratio = readconfigfromdb('currencyratio');
	$transaction['amount'] = round($amount * $ratio);
	$transaction['description'] = $description;
	$transaction['id_from'] = $id_from;
	//Lookup id_to first
	$to_user = $db->fetchAssoc('SELECT * FROM users WHERE letscode = ?', array($myletsgroup['localletscode']));

	$transaction['id_to'] = $to_user['id'];
	//Real_to has to be set by a soap call
	$mysoapurl = $myletsgroup['elassoapurl'] .'/wsdlelas.php?wsdl';
	$myapikey = $myletsgroup['remoteapikey'];
	$client = new nusoap_client($mysoapurl, true);
	$result = $client->call('userbyletscode', array('apikey' => $myapikey, 'letscode' => $letscode_to));
	$err = $client->getError();
	if (!$err)
	{
		$transaction['real_to'] = $result;
	}

	$transaction['transid'] = $transid;
	$transaction['date'] = date('Y-m-d H:i:s');

	$error_list = array();

	if (!isset($transaction['description'])|| (trim($transaction['description'] ) == ''))
	{
		$error_list['description']='Dienst is niet ingevuld';
	}

	//amount may not be empty
	$transaction['amount'] = trim($transaction['amount']);

	if (!isset($transaction['amount']) || (trim($transaction['amount'] ) == '' || !$transaction['amount']))
	{
		$error_list['amount'] = 'Bedrag is niet ingevuld';
	}

	//userfrom must exist
	$fromuser = readuser($transaction['id_from']);
	if(!$fromuser)
	{
		$error_list['id_from'] = 'Gebruiker bestaat niet';
	}

	//userto must exist
	$touser = readuser($transaction['id_to']);
	if(!$touser)
	{
		$error_list['id_to'] = 'Gebruiker bestaat niet';
	}

	//userfrom and userto should not be the same
	if($fromuser['letscode'] == $touser['letscode']){
		$error_list['id']='Van en Aan zijn hetzelfde';
	}

	if (!isset($transaction['date'])|| (trim($transaction['date'] )==''))
	{
		$error_list['date']='Datum is niet ingevuld';
	}
	else if(strtotime($transaction['date']) == -1)
	{
		$error_list['date']='Fout in datumformaat (jjjj-mm-dd)';
	}

	if(!empty($error_list))
	{
		echo "\nVALIDATION ERRORS\n";
		var_dump($error_list);
		echo "\Tried to commit:\n";
		var_dump($transaction);
		echo "\n";
	}
	else
	{
		$id = insert_transaction($transaction);
	}

	if($id)
	{
		$result = 'SUCCESS';
		log_event('', 'Trans', 'Local commit of interlets transaction succeeded');
		$transaction['amount'] = round($transaction['amount']);
		$transaction['id'] = $id;
		mail_transaction($transaction);
		unqueue($transid);
	}
	else
	{
		$result = 'FAILED';
		log_event('','Trans','Local commit of $transid failed');
		//FIXME Replace with something less spammy (1 mail per 15 minutes);

		$subject = 'Interlets FAILURE!';

		$text = 'WARNING: LOCAL COMMIT OF TRANSACTION $transid FAILED!!!  This means the transaction is not balanced now!';

		mail_q(array('to' => 'admin', 'subject' => $subject, 'text' => $text));
	}

	echo $result;
	echo "\n";
	return $result;
}
