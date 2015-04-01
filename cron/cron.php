<?php
ob_start();

$r = "\r\n";

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

// require_once($rootpath."cron/inc_stats.php");

require_once($rootpath."includes/inc_mailfunctions.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_saldofunctions.php");

require_once($rootpath."includes/inc_news.php");

require_once $rootpath . 'includes/inc_eventlog.php';

header('Content-Type:text/plain');
echo '*** Cron eLAS-Heroku ***' . $r;

echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r;

$schemas = $domains = $schema_cron_timestamps = $schema_interletsqs = $table = array();

foreach ($_ENV as $key => $schema)
{
	if (strpos($key, 'ELAS_SCHEMA_') === 0)
	{
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

	$letsgroups_typeahead_update = (json_decode($redis->get('letsgroups_typeahead_update'), true)) ?: array();

	$unvalid_apikeys = (json_decode($redis->get($schema . '_typeahead_unvalid_apikeys'), true)) ?: array();

	$failed_connections = (json_decode($redis->get($schema . '_typeahead_failed_connections'), true)) ?: array();

	$now = time();

	foreach ($letsgroups as $letsgroup)
	{
		if ($unvalid_apikeys[$letsgroup['remoteapikey']])
		{
			continue;
		}

		if ($failed_connections[$letsgroup['url']])
		{
			continue;
		}

		if (!$letsgroups_typeahead_update[$letsgroup['url']])
		{
			break;
		}

		if ($letsgroups_typeahead_update[$letsgroup['url']] < $now - 21600)	// 6 hours 
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
			
			$failed_connections[$letsgroup['url']] = 1;
			$redis->set($schema . '_typeahead_failed_connections', $failed_connections);
			$redis->expire($schema . '_typeahead_failed_connections', 43200);  // 12 hours
		}
		else
		{
			$token = $client->call('gettoken', array('apikey' => $apikey));
			$err = $client->getError();
			if ($err)
			{
				echo $err_group . 'Kan geen token krijgen.' . $r;

				$unvalid_apikeys[$letsgroup['remoteapikey']] = 1;
				$redis->set($schema . '_typeahead_unvalid_apikeys', $unvalid_apikeys);
				$redis->expire($schema . '_typeahead_unvalid_apikeys',86400);  // 24 hours
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

		$redis->set('letsgroup_' . $letsgroup['url'] . '_typeahead', json_encode($users));
		$redis->expire('letsgroup_' . $letsgroup['url'] . '_typeahead', 86400);

		$letsgroups_typeahead_update[$letsgroup['url']] = $now;

		$redis->set('letsgroups_typeahead_update', json_encode($letsgroups_typeahead_update));
		$redis->expire('letsgroups_typeahead_update', 86400);

		echo '----------------------------------------------------' . $r;
		echo 'letsgroups_typeahead_update ' . "\n";
		echo 'letsgroup_' . $letsgroup['url'] . '_users_typeahead' . $r;
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

if(check_timestamp($lastrun_ary['saldo'], readconfigfromdb("saldofreqdays") * 1440))
{
	automail_saldo();
}

// Auto mail messages that have expired to the admin
if(check_timestamp($lastrun_ary['admin_exp_msg'], readconfigfromdb("adminmsgexpfreqdays") * 1440) && readconfigfromdb("adminmsgexp"))
{
	automail_admin_exp_msg();
}

// Check for and mail expired messages to the user
if(check_timestamp($lastrun_ary['user_exp_msgs'], 1440) && readconfigfromdb("msgexpwarnenabled"))
{
	check_user_exp_msgs();
}


// Clean up expired messages after the grace period
if(check_timestamp($lastrun_ary['cleanup_messages'], 1440) && readconfigfromdb("msgcleanupenabled"))
{
	cleanup_messages();

	// remove orphaned images.
	$query = 'SELECT mp.id, mp.\'Picturefile\'
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


// Update counts for each message category
if(check_timestamp($lastrun_ary['cat_update_count'], 60))
{
	cat_update_count();
}

// Update the cached saldo
if(check_timestamp($lastrun_ary['saldo_update'], 60))
{
	saldo_update();
}

// Clean up expired news items
if(check_timestamp($lastrun_ary['cleanup_news'], 1440))
{
	cleanup_news();
}

// Clean up expired tokens
if(check_timestamp($lastrun_ary['cleanup_tokens'], 60))
{
	cleanup_tokens();
}

// interletsq
if(check_timestamp($lastrun_ary['processqueue'], 5))
{
	require_once $rootpath . 'interlets/processqueue.php';
	write_timestamp('processqueue');
}

if(check_timestamp($lastrun_ary['publish_news'], 30))
{
	publish_news();
}

$redis->set($schema . '_interletsq', '');
$redis->set($schema . '_cron_timestamp', time());

echo "\nCron run finished\n";
exit;

////////////////////

function cat_update_count()
{
	echo "Running cat_update_count\n";
        $catlist = get_cat();
        foreach ($catlist AS $key => $value){
                $cat_id = $value["id"];
                update_stat_msgs($cat_id);
        }

	write_timestamp("cat_update_count");
}

function saldo_update()
{
	global $db;
	echo "Running saldo_update ...";

	$query = "SELECT * FROM users";
	$userrows = $db->GetArray($query);

	foreach ($userrows AS $key => $value){
		//echo $value["id"] ." ";
		update_saldo($value["id"]);
	}
	echo "\n";
	write_timestamp("saldo_update");
}

function publish_news()
{
	global $db;
	global $baseurl;

    echo "Running publish_news...\n";

    $query = 'SELECT * FROM news WHERE approved = true AND published IS NULL OR published = false;';
	$newsitems = $db->GetArray($query);

    foreach ($newsitems AS $key => $value){
		mail_news($value["id"]);

		$q2 = "UPDATE news SET published = true WHERE id=" . $value["id"];
		$db->Execute($q2);
	}
	write_timestamp("publish_news");
}

function cleanup_messages()
{
	// Fetch a list of all expired messages that are beyond the grace period and delete them
	echo "Running cleanup_messages\n";
	do_auto_cleanup_messages();
	do_auto_cleanup_inactive_messages();
	write_timestamp("cleanup_messages");
}

function cleanup_tokens()
{
	echo "Running cleanup_tokens\n";
	do_cleanup_tokens();
	write_timestamp("cleanup_tokens");
}

function cleanup_news()
{
	echo "Running cleanup_news\n";
	do_cleanup_news();
	write_timestamp("cleanup_news");
}

function check_user_exp_msgs()
{
	//Fetch a list of all non-expired messages that havent sent a notification out yet and mail the user
	echo "Running check_user_exp_msgs\n";
	$msgexpwarningdays = readconfigfromdb("msgexpwarningdays");
	$msgcleanupdays = readconfigfromdb("msgexpcleanupdays");
	$warn_messages = get_warn_messages($msgexpwarningdays);
	foreach ($warn_messages AS $key => $value){
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
	$warn_messages = get_expired_messages();
	foreach ($warn_messages AS $key => $value){
		//For each of these, we need to fetch the user's mailaddress and send him a mail.
		echo "Found phase 2 expired message " .$value["id"];
		$user = get_user_maildetails($value["id_user"]);
		$username = $user["name"];

		$content = "Beste $username\n\nJe vraag of aanbod '" .$value["content"] ."'";
		$content .= " in eLAS is vervallen. Als je het niet verlengt wordt het $msgcleanupdays na de vervaldag automatisch verwijderd.";
		$mailaddr = $user["emailaddress"];
		$subject = "Je V/A in eLAS is vervallen";
		mail_user_expwarn($mailaddr,$subject,$content);
		mark_expwarn($value["id"],2);
	}

	// Finally, clear all the old flags with a single SQL statement
	// UPDATE messages SET exp_user_warn = 0 WHERE validity > now + 10
	do_clear_msgflags();

	// Write the timestamp
	write_timestamp("user_exp_msgs");
}

function automail_admin_exp_msg()
{
	// Fetch a list of all expired messages and mail them to the admin
	echo "Running automail_admin_exp_msg\n";
	global $db;
	$today = date("Y-m-d");
	$query = "SELECT users.name AS username, messages.content AS message, messages.id AS mid, messages.validity AS validity FROM messages,users WHERE users.status <> 0 AND messages.id_user = users.id AND validity <= '" .$today ."'";
	$messages = $db->GetArray($query);

	mail_admin_expmsg($messages);

	write_timestamp("admin_exp_msg");
}

function automail_saldo()
{
	// Get all users that want their saldo auto-mailed.
	echo "Running automail_saldo\n";
	global $db;
	$query = 'SELECT users.id,
		users.name, users.saldo AS saldo,
		contact.value AS cvalue FROM users,
		contact, type_contact 
	WHERE users.id = contact.id_user
		AND contact.id_type_contact = type_contact.id
		AND type_contact.abbrev = \'mail\'
		AND users.status <> 0';
	$query .= (readconfigfromdb('forcesaldomail')) ? '' : ' AND users.cron_saldo = 1';
	$users = $db->GetArray($query);

	foreach($users as $key => $value)
	{
		$balance = $value["saldo"];
		mail_balance($value["cvalue"], $mybalance);
	}

	$from_address_transactions = readconfigfromdb("from_address_transactions");
	if (!empty($from_address_transactions))
	{
		$mailfrom .= trim($from_address_transactions);
	}
	else
	{
		echo "Mail from address is not set in configuration\n";
		return 0;
	}

    $subject = "[eLAS-". readconfigfromdb("systemtag") ."] - Saldo en laatste vraag en aanbod.";

    $content = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n";
    if (!readconfigfromdb('forcesaldomail'))
    {
		$content .= "Je ontvangt deze mail omdat je de optie 'Mail saldo' in eLAS hebt geactiveerd,\n zet deze uit om deze mails niet meer te ontvangen.\n";
	}

	$currency = readconfigfromdb("currency");
	$mailcontent .= "\nJe huidig LETS saldo is " .$balance ." " .$currency ."\n";

	$mailcontent .= "\nDe eLAS MailSaldo Robot\n";
	sendemail($mailfrom, $value['cvalue'], $subject, $mailcontent);
	
	//Timestamp this run
	write_timestamp("saldo");
}
