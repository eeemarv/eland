<?php
ob_start();

$r = "\n";
$now = gmdate('Y-m-d H:i:s');

$php_sapi_name = php_sapi_name();

if ($php_sapi_name == 'cli')
{
	echo 'The cron should not run from the cli but from the http web server.' . $r;
	exit;
}

defined('__DIR__') or define('__DIR__', dirname(__FILE__));
chdir(__DIR__);

$rootpath = "../";
$role = 'anonymous';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

require_once $rootpath . 'cron/inc_cron.php';
require_once $rootpath . 'cron/inc_upgrade.php';
require_once $rootpath . 'cron/inc_processqueue.php';

// require_once($rootpath."cron/inc_stats.php");

require_once($rootpath."includes/inc_mailfunctions.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_saldofunctions.php");

require_once($rootpath."includes/inc_news.php");

require_once $rootpath . 'includes/inc_eventlog.php';

$s3 = Aws\S3\S3Client::factory(array(
	'signature'	=> 'v4',
	'region'	=>'eu-central-1',
));

header('Content-Type:text/plain');
echo '*** Cron eLAS-Heroku ***' . $r;

echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r;

// select in which schema to perform updates
$schemas = $domains = $schema_cron_timestamps = $schema_interletsqs = $table = array();

$schemas_db = ($db->GetArray('select schema_name from information_schema.schemata')) ?: array();
$schemas_db = array_map(function($row){ return $row['schema_name']; }, $schemas_db);
$schemas_db = array_fill_keys($schemas_db, true);

foreach ($_ENV as $key => $schema)
{
	if (strpos($key, 'ELAS_SCHEMA_') !== 0 || (!isset($schemas_db[$schema])))
	{
		continue;
	}
	
	$domain = str_replace('ELAS_SCHEMA_', '', $key);

	$schemas[$domain] = $schema;

	$domain = str_replace('___', '-', $domain);
	$domain = str_replace('__', '.', $domain);
	$domain = strtolower($domain);

	$domains[$schema] = $domain;

	$schema_cron_timestamps[$schema] = (int) $redis->get($schema . '_cron_timestamp');

	if ($interletsq = (int) $redis->get($schema . '_interletsq'))
	{
		$schema_interletsqs[$schema] = $interletsq;
	}
}

unset($schema, $domain);

if (count($schemas))
{
	asort($schema_cron_timestamps);

	if (count($schema_interletsqs))
	{
		list($schema_interletsq_min) = array_keys($schema_interletsqs, min($schema_interletsqs));
	}

	echo 'Schema (domain): last cron timestamp : interletsqueue timestamp' . $r;
	echo '---------------------------------------------------------------' . $r;
	foreach ($schema_cron_timestamps as $schema_n => $timestamp)
	{
		echo $schema_n . ' (' . $domains[$schema_n] . '): ' . $timestamp . ' : ';
		echo ($schema_interletsqs[$schema_n]) ? $schema_interletsqs[$schema_n] : 0;

		if ((!isset($selected) && !isset($schema_interletsq_min))
			|| (isset($schema_interletsq_min) && $schema_interletsq_min == $schema_n))
		{
			$schema = $schema_n;
			$db->Execute('SET search_path TO ' . $schema);
			$selected = true;
			echo ' (selected)';
		}
		echo $r;
	}
}
else
{
	echo '-- No installed domains found. --' . $r;
	exit;
}

echo "*** Cron system running [" . $schema . ' ' . $domains[$schema] . ' ' . readconfigfromdb('systemtag') ."] ***\n\n";

// begin typeahaed update (when interletsq is empty) for one group

if (!isset($schema_interletsq_min))
{

	$letsgroups = $db->GetArray('SELECT *
		FROM letsgroups
		WHERE apimethod = \'elassoap\'
			AND remoteapikey IS NOT NULL');

	foreach ($letsgroups as $letsgroup)
	{
		if ($redis->get($schema . '_typeahead_failed_' . $letsgroup['remoteapikey'])
			|| $redis->get($schema . '_typeahead_failed_' . $letsgroup['url']))
		{
			continue;
		}

		if (!$redis->get($letsgroup['url'] . '_typeahead_updated'))
		{
			break;
		}

		unset($letsgroup);
	}

	if (isset($letsgroup))
	{
		$err_group = $letsgroup['groupname'] . ': ';

		$soapurl = ($letsgroup['elassoapurl']) ? $letsgroup['elassoapurl'] : $letsgroup['url'] . '/soap';
		$soapurl = $soapurl ."/wsdlelas.php?wsdl";
		$apikey = $letsgroup["remoteapikey"];
		$client = new nusoap_client($soapurl, true);
		$err = $client->getError();
		if ($err)
		{
			echo $err_group . 'Kan geen verbinding maken.' . $r;

			$redis_key = $schema . '_typeahead_failed_' . $letsgroup['url'];
			$redis->set($redis_key, '1');
			$redis->expire($redis_key, 43200);  // 12 hours
		}
		else
		{
			$token = $client->call('gettoken', array('apikey' => $apikey));
			$err = $client->getError();
			if ($err)
			{
				echo $err_group . 'Kan geen token krijgen.' . $r;

				$unvalid_apikeys[$letsgroup['remoteapikey']] = 1;
				$redis_key = $schema . '_typeahead_failed_' . $letsgroup['remoteapikey'];
				$redis->set($redis_key, '1');
				$redis->expire($redis_key, 43200);  // 12 hours
			}
		}

		$client = new Goutte\Client();

		$crawler = $client->request('GET', $letsgroup['url'] . '/login.php?token=' . $token);
		$crawler = $client->request('GET', $letsgroup['url'] . '/rendermembers.php');

		$users = array();

		$crawler->filter('table > tr')->first()->nextAll()->each(function ($node) use (&$users)
		{
			$user = array();

			$td = $node->filter('td')->first();
			$bgcolor = $td->attr('bgcolor');
			$postcode = $td->siblings()->eq(3)->text();

			$user['c'] = $td->text();
			$user['n'] = $td->nextAll()->text();

			if ($bgcolor)
			{
				$user['s'] = (strtolower(substr($bgcolor, 1, 1)) > 'c') ? 2 : 3;
			}

			if ($postcode)
			{
				$user['p'] = $postcode;
			} 

			$users[] = $user;
		});

		$redis_data_key = $letsgroup['url'] . '_typeahead_data';
		$data_string = json_encode($users);
		
		if ($data_string != $redis->get($redis_data_key))
		{
			$redis_thumbprint_key = $letsgroup['url'] . '_typeahead_thumbprint';
			$redis->set($redis_thumbprint_key, time());
			$redis->expire($redis_thumbprint_key, 5184000);	// 60 days
			$redis->set($redis_data_key, $data_string);
		}
		$redis->expire($redis_data_key, 86400);		// 1 day

		$redis_refresh_key = $letsgroup['url'] . '_typeahead_updated';
		$redis->set($redis_refresh_key, '1');
		$redis->expire($redis_refresh_key, 21600);		// 6 hours

		echo '----------------------------------------------------' . $r;
		echo $redis_data_key . $r;
		echo $redis_refresh_key . $r;
		echo 'user count: ' . count($users) . "\n";
		echo '----------------------------------------------------' . $r;
		echo 'end Cron ' . "\n";

		$redis->set($schema . '_cron_timestamp', time());

		exit;
	}
	else
	{
		echo '-- no letsgroup typeahead update needed -- ' . $r;
	}
}
else
{
	echo '-- priority to interletsq, no letsgroup typeahead updated --' . $r;
}

/// end typeahead update

$lastrun_ary = $db->GetAssoc('select cronjob, lastrun from cron');

run_cronjob('processqueue');

run_cronjob('saldo', 86400 * readconfigfromdb("saldofreqdays"));

function saldo()
{
	$mandrill = new Mandrill();

	// Get all users that want their saldo auto-mailed.
	echo "Running automail_saldo\n";
	global $db;
	$query = 'SELECT u.id,
			u.name, u.saldo,
			c.value
		FROM users u, contact c, type_contact tc
		WHERE u.id = c.id_user
			AND c.id_type_contact = tc.id
			AND tc.abbrev = \'mail\'
			AND u.status in (1, 2)';
	$query .= (readconfigfromdb('forcesaldomail')) ? '' : ' AND u.cron_saldo = \'t\'';
	$users = $db->GetArray($query);

/*
	foreach($users as $key => $value)
	{
		$balance = $value["saldo"];
		mail_balance($value["cvalue"], $mybalance);
	}
*/

	$messages = $db->GetArray('SELECT m.id, m.content, m.description
		FROM messages m
		WHERE m.cdate => ' .  date('Y-m-d H:i:S', time() - readconfigfromdb('saldofreqdays') * 86400));

	$r = "\n\r";
	$t = 'Saldo';
	$u = '-----';
	$text .= $t . $r . $u . $r;
	$html .= '<h1>' . $t . '</h1>';
	$t = 'Je huidige saldo bedraagt |BALANCE| ' . readconfigfromdb('currency');
	$text .= $t . $r;
	$html .= '<p>' . $t . '</p>';
	$t ='Recent LETS vraag en aanbod';
	$u ='---------------------------';
	$text .= $t . $r . $u . $r;
	$html .= '<h1>' . $t . '</h1>';
	$t = 'Deze mail bevat LETS vraag en aanbod dat in de afgelopen ' . readconfigfromdb('saldofreqdays') .
		' dagen in eLAS is geplaatst. Contactgegevens kan je zien door de naam van
		de persoon met je muis aan te wijzen.
		Klik op de persoon om te e-mailen. Een kaart of routebeschrijving krijg je
		door op (?) te klikken. Klik op (+) om een bericht te bekijken op eLAS.
		Neem <a href="mailto:' . readconfigfromdb('support') .
		'">contact</a> op met ons als je problemen ervaart.';
	$text .= $t . $r;
	$html .= '<p>' . $t . '</p>';


	$from = readconfigfromdb("from_address_transactions");
	if (empty($from))
	{
		echo "Mail from_address_transactions is not set in configuration\n";
		return 0;
	}


	$content = "Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub\r\n";
	if (!readconfigfromdb('forcesaldomail'))
	{
		$content .= "Je ontvangt deze mail omdat je de optie 'Mail saldo' in eLAS hebt geactiveerd,\n zet deze uit om deze mails niet meer te ontvangen.\n";
	}

	$currency = readconfigfromdb("currency");
	$mailcontent .= "\nJe huidig LETS saldo is " .$balance ." " .$currency ."\n";

	$mailcontent .= "\nDe eLAS MailSaldo Robot\n";
	sendemail($mailfrom, $value['cvalue'], $subject, $mailcontent);

	$message = array(
		'subject'		=> '[eLAS-'. readconfigfromdb('systemtag') .'] - Saldo, recent vraag en aanbod en nieuws.',
		'text'			=> $text,
		'html'			=> $html,
		'from_email'	=> $from,
		'to'			=> $to,
	);

	try
	{
		$mandrill = new Mandrill(); 
		$mandrill->messages->send($message, true);
	}
	catch (Mandrill_Error $e)
	{
		// Mandrill errors are thrown as exceptions
		log_event($s_id, 'mail', 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage());
		return;
	}

	$to = (is_array($to)) ? implode(', ', $to) : $to;

	log_event($s_id, 'mail', 'Saldomail sent, subject: ' . $subject . ', from: ' . $from . ', to: ' . $to);
}

run_cronjob('admin_exp_msg', 86400 * readconfigfromdb("adminmsgexpfreqdays"), readconfigfromdb("adminmsgexp"));

function admin_exp_msg()
{
	// Fetch a list of all expired messages and mail them to the admin
	echo "Running automail_admin_exp_msg\n";
	global $db;
	$today = date("Y-m-d");
	$query = "SELECT u.name AS username, m.content AS message, m.id AS mid, m.validity AS validity
		FROM messages m, users u
		WHERE users.status <> 0
			AND m.id_user = u.id
			AND validity <= '" .$today ."'";
	$messages = $db->GetArray($query);

	$admin = readconfigfromdb("admin");
	if (empty($admin))
	{
		echo "No admin E-mail address specified in config\n";
		return false;
	}
	else
	{
	   $mailto = $admin;
	}

	$from_address_transactions = readconfigfromdb("from_address_transactions");

	if (!empty($from_address_transactions))
	{
		$mailfrom .= "From: ".trim($from_address_transactions)."\r\n";
	}
	else
	{
		echo "Mail from address is not set in configuration\n";
		return 0;
	}

	$systemtag = readconfigfromdb("systemtag");
	$mailsubject = "[eLAS-".$systemtag ."] - Rapport vervallen V/A";

	$mailcontent = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n\n";
	$mailcontent .= "ID\tUser\tMessage\n";
	
	foreach($messages as $key => $value)
	{
		$mailcontent .=  $value["mid"] ."\t" .$value["username"] ."\t" .$value["message"] ."\t" .$value["validity"] ."\n";
	}

	$mailcontent .=  "\n\n";

	sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);

	return true;
}

// run_cronjob('user_exp_msgs', 86400, readconfigfromdb("msgexpwarnenabled"));

function user_exp_msgs()
{
	//Fetch a list of all non-expired messages that havent sent a notification out yet and mail the user
	$msgexpwarningdays = readconfigfromdb("msgexpwarningdays");
	$msgcleanupdays = readconfigfromdb("msgexpcleanupdays");
	
	$warn_messages = get_warn_messages($msgexpwarningdays);
	
	foreach ($warn_messages AS $key => $value)
	{
		//For each of these, we need to fetch the user's mailaddress and send him a mail.
		echo "Found new expired message " .$value["id"];
		$user = get_user_maildetails($value["id_user"]);
		$username = $user["name"];

		$content = "Beste $username\n\nJe vraag of aanbod '" .$value["content"] ."'";
		$content .= " in eLAS gaat over " .$msgexpwarningdays;
		$content .= " dagen vervallen.  Om dit te voorkomen kan je inloggen op eLAS en onder de optie 'Mijn Vraag & Aanbod' voor verlengen kiezen.";
		$content .= "\n\nAls je niets doet verdwijnt dit V/A $msgcleanupdays na de vervaldag uit je lijst.";
		$mailaddr = $user["emailaddress"];
		$subject = "Je V/A in eLAS gaat vervallen";
		mail_user_expwarn($mailaddr,$subject,$content);
		mark_expwarn($value["id"],1);
	}

	//Fetch a list of expired messages and warn the user again.

	$warn_messages  = $db->GetArray('SELECT * FROM messages WHERE exp_user_warn = \'f\' AND validity < \'' . $now . '\'');
	
	foreach ($warn_messages AS $key => $value)
	{
		//For each of these, we need to fetch the user's mailaddress and send him a mail.
		echo "Found phase 2 expired message " .$value["id"];
		$user = get_user_maildetails($value["id_user"]);
		$username = $user["name"];

		$content = "Beste $username\n\nJe vraag of aanbod '" .$value["content"] ."'";
		$content .= ' in eLAS is vervallen. Als je het niet verlengt wordt het ';
		$content .= $msgcleanupdays . ' dagen na de vervaldag automatisch verwijderd.';
		$mailaddr = $user["emailaddress"];
		$subject = "Je V/A in eLAS is vervallen";
		mail_user_expwarn($mailaddr,$subject,$content);
		$db->Execute('UPDATE messages set exp_user_warn = \'t\' WHERE id = ' .$value['id']);
	}

	// Finally, clear all the old flags with a single SQL statement
	// UPDATE messages SET exp_user_warn = 0 WHERE validity > now + 10

	$testdate = gmdate('Y-m-d H:i:s', time() + ($msgexpwarningdays * 86400));
	$query = "UPDATE messages SET exp_user_warn = 'f' WHERE validity > '" .$testdate ."'";
	$db->Execute($query);

	return true;
}

run_cronjob('cleanup_messages', 86400);

function cleanup_messages()
{
	do_auto_cleanup_messages();
	do_auto_cleanup_inactive_messages();
	
	// remove orphaned images.
	$query = 'SELECT mp.id, mp."PictureFile"
		FROM msgpictures mp
		LEFT JOIN messages m ON mp.msgid = m.id
		WHERE m.id IS NULL';
	$orphan_images = $db->GetAssoc($query);

	if (count($orphan_images))
	{
		foreach ($orphan_images as $id => $file)
		{
			$result = $s3->deleteObject(array(
				'Bucket' => getenv('S3_BUCKET'),
				'Key'    => $file,
			));

			echo $result . $r;
			
			$db->Execute('DELETE FROM msgpictures WHERE id = ' . $id);
		}
	}
}

run_cronjob('cat_update_count', 3600);

// Update counts for each category
function cat_update_count()
{
	global $db;
	
	$offer_count = $db->GetAssoc('SELECT m.id_category, COUNT(m.*)
		FROM messages m, users u
		WHERE  m.id_user = u.id
			AND u.status IN (1, 2, 3)
			AND msg_type = 1
		GROUP BY m.id_category');
		
	$want_count = $db->GetAssoc('SELECT m.id_category, COUNT(m.*)
		FROM messages m, users u
		WHERE  m.id_user = u.id
			AND u.status IN (1, 2, 3)
			AND msg_type = 0
		GROUP BY m.id_category');
		
	$all_cat = $db->GetArray('SELECT id, stat_msgs_offers, stat_msgs_wanted
		FROM categories
		WHERE id_parent IS NOT NULL');

	foreach ($all_cat as $val)
	{
		$offers = $val['stat_msgs_offers'];
		$wants = $val['stat_msgs_wanted'];
		$id = $val['id'];

		if ($want_count[$id] == $wants && $offer_count[$id] == $offers)
		{
			continue;
		}

		$stats = array(
			'stat_msgs_offers'	=> ($offer_count[$id]) ?: 0,
			'stat_msgs_wanted'	=> ($want_count[$id]) ?: 0,
		);
		
		$db->AutoExecute('categories', $stats, 'UPDATE', 'id = ' . $id);
	}

	return true;
}

run_cronjob('saldo_update', 3600);

function saldo_update()
{
	global $db;
	$query = "SELECT * FROM users";
	$userrows = $db->GetArray($query);

	foreach ($userrows AS $key => $value){
		//echo $value["id"] ." ";
		update_saldo($value["id"]);
	}
	echo "\n";

	return true;
}

run_cronjob('cleanup_news', 86400);

function cleanup_news()
{
    global $db, $now;
	return ($db->Execute("DELETE FROM news WHERE itemdate < '" .$now ."' AND sticky = 'f'")) ? true : false;
}

run_cronjob('cleanup_tokens', 3600);

function cleanup_tokens()
{
	global $db, $now;
	return ($db->Execute("DELETE FROM tokens WHERE validity < '" .$now ."'")) ? true : false;
}

run_cronjob('publish_news', 1800);

function publish_news()
{
	global $db;

    $query = 'SELECT id FROM news WHERE approved = \'t\' AND published IS NULL OR published = \'f\'';
	$newsitems = $db->GetAssoc($query);

    foreach ($newsitems AS $key => $value){
		mail_news($value["id"]);

		$q2 = "UPDATE news SET published = \'t\' WHERE id = " . $value["id"];
		$db->Execute($q2);
	}

	return true;
}

$redis->set($schema . '_interletsq', '');
$redis->set($schema . '_cron_timestamp', time());

echo "*** Cron run finished ***\n";
exit;

////////////////////

function run_cronjob($name, $interval = 300, $enabled = null)
{
	global $db;
	static $lastrun_ary;

	if (!(isset($lastrun_ary) && is_array($lastrun_ary)))
	{
		$lastrun_ary = $db->GetAssoc('select cronjob, lastrun from cron');
	}

	if (!((time() - $interval > ((isset($lastrun_ary[$name])) ? strtotime($lastrun_ary[$name]) : 0)) & ($enabled || !isset($enabled))))
	{
		echo 'Cronjob: ' . $name . ' not running.' . "\n\r";
		return;
	}

	echo 'Running ' . $name . "\n";

	$updated = call_user_func($name);

	if (isset($lastrun_ary[$name]))
	{
		$db->Execute('update cron set lastrun = \'' . gmdate('Y-m-d H:i:s') . '\' where cronjob = \'' . $name . '\'');
	}
	else
	{
		$db->Execute('insert into cron (cronjob, lastrun) values (\'' . $name . '\', \'' . gmdate('Y-m-d H:i:s') . '\')');
	}
	log_event(' ', 'Cron', 'Cronjob ' . $name . ' finished.');
	echo 'Cronjob ' . $name . ' finished.' . "\n\r";

	return $updated;
}
