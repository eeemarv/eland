<?php
ob_start();

$r = "<br>\r\n";
$now = gmdate('Y-m-d H:i:s');

$php_sapi_name = php_sapi_name();

if ($php_sapi_name == 'cli')
{
	echo 'The cron should not run from the cli but from the http web server.' . $r;
	exit;
}

defined('__DIR__') or define('__DIR__', dirname(__FILE__));
chdir(__DIR__);

$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
$rootpath = '../';
$role = 'anonymous';
require_once $rootpath . 'includes/inc_default.php';

require_once $rootpath . 'cron/inc_upgrade.php';
require_once $rootpath . 'cron/inc_processqueue.php';
require_once $rootpath . 'cron/inc_saldo_mail.php';

require_once $rootpath . 'includes/inc_mailfunctions.php';
require_once $rootpath . 'includes/inc_userinfo.php';

require_once $rootpath . 'includes/inc_eventlog.php';

$s3 = Aws\S3\S3Client::factory(array(
	'signature'	=> 'v4',
	'region'	=> 'eu-central-1',
	'version'	=> '2006-03-01',
));

header('Content-Type:text/html');
echo '*** Cron eLAS-Heroku ***' . $r;

echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r;

// select in which schema to perform updates
$schemas = $domains = $schema_lastrun_ary = $schema_interletsq_ary = array();

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

	$domain = str_replace('____', ':', $domain);
	$domain = str_replace('___', '-', $domain);
	$domain = str_replace('__', '.', $domain);
	$domain = strtolower($domain);

	$domains[$schema] = $domain;

	$lastrun = $db->GetOne('select max(lastrun) from ' . $schema . '.cron');
	$schema_lastrun_ary[$schema] = ($lastrun) ?: 0;

	if ($date_interletsq = $db->GetOne('select min(date_created)
		from '. $schema . '.interletsq
		where last_status = \'NEW\''))
	{
		$schema_interletsq_ary[$schema] = $date_interletsq;
	}
}

unset($schema, $domain);

if (count($schemas))
{
	asort($schema_lastrun_ary);

	if (count($schema_interletsq_ary))
	{
		list($schema_interletsq_min) = array_keys($schema_interletsq_ary, min($schema_interletsq_ary));
	}

	echo 'Schema (domain): last cron timestamp : interletsqueue timestamp' . $r;
	echo '---------------------------------------------------------------' . $r;
	foreach ($schema_lastrun_ary as $schema_n => $time)
	{
		echo $schema_n . ' (' . $domains[$schema_n] . '): ' . $time;
		echo (isset($schema_interletsq_ary[$schema_n])) ? ' interletsq: ' . $schema_interletsq_ary[$schema_n] : '';

		if ((!isset($selected) && !isset($schema_interletsq_min))
			|| (isset($schema_interletsq_min) && $schema_interletsq_min == $schema_n))
		{
			$schema = $schema_n;
			echo ' (selected)';			
			$db->Execute('SET search_path TO ' . $schema);
			$selected = true;
		}
		echo $r;
	}
}
else
{
	echo '-- No installed domains found. --' . $r;
	exit;
}

echo "*** Cron system running [" . $schema . ' ' . $domains[$schema] . ' ' . readconfigfromdb('systemtag') ."] ***" . $r;

$elas_log->set_schema($schema);

$base_url = $http . $domains[$schema];

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
		$redis->expire($redis_refresh_key, 43200);		// 12 hours

		$user_count = count($users);

		$redis_user_count_key = $letsgroup['url'] . '_active_user_count';
		$redis->set($redis_user_count_key, $user_count);
		$redis->expire($redis_user_count_key, 86400); // 1 day

		log_event('', 'Cron', 'typeahead data fetched of ' . $user_count . ' users from group ' . $letsgroup['groupname']);

		echo '----------------------------------------------------' . $r;
		echo $redis_data_key . $r;
		echo $redis_refresh_key . $r;
		echo 'user count: ' . $user_count . $r;
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
	
	run_cronjob('processqueue');
}

run_cronjob('saldo', 86400 *  readconfigfromdb('saldofreqdays'));

run_cronjob('admin_exp_msg', 86400 * readconfigfromdb('adminmsgexpfreqdays'), readconfigfromdb('adminmsgexp'));

function admin_exp_msg()
{
	// Fetch a list of all expired messages and mail them to the admin
	global $db, $now, $r, $base_url;
	
	$query = "SELECT u.name AS username, m.content AS message, m.id AS mid, m.validity AS validity
		FROM messages m, users u
		WHERE users.status <> 0
			AND m.id_user = u.id
			AND validity <= '" .$now ."'";
	$messages = $db->GetArray($query);

	$admin = readconfigfromdb("admin");
	if (empty($admin))
	{
		echo "No admin E-mail address specified in config" . $r;
		return false;
	}
	else
	{
	   $mailto = $admin;
	}

	$from_address_transactions = readconfigfromdb('from_address_transactions');

	if (!empty($from_address_transactions))
	{
		$mailfrom .= "From: ".trim($from_address_transactions);
	}
	else
	{
		echo "Mail from address is not set in configuration" . $r;
		return 0;
	}

	$systemtag = readconfigfromdb("systemtag");
	$mailsubject = "[eLAS-".$systemtag ."] - Rapport vervallen V/A";

	$mailcontent = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --" . $r;
	$mailcontent .= "ID\tUser\tMessage\n";
	
	foreach($messages as $key => $value)
	{
		$mailcontent .=  $value["mid"] ."\t" .$value["username"] ."\t" .$value["message"] ."\t" .$value["validity"] ."\n";
		$mailcontent .= $base_url . '\messages\view?id=' . $value['mid'] . " \n";
	}

	$mailcontent .=  $r;

	sendemail($mailfrom,$mailto,$mailsubject,$mailcontent);

	return true;
}

run_cronjob('user_exp_msgs', 86400, readconfigfromdb('msgexpwarnenabled'));

function user_exp_msgs()
{
	global $db, $now, $base_url;
	//Fetch a list of all non-expired messages that havent sent a notification out yet and mail the user
	$msgcleanupdays = readconfigfromdb("msgexpcleanupdays");
	$warn_messages  = $db->GetArray("SELECT m.*
		FROM messages m
			WHERE m.exp_user_warn = 'f'
				AND m.validity < '" .$now ."'");

	// $warn_messages = get_warn_messages($msgexpwarningdays);
	
	foreach ($warn_messages AS $key => $value)
	{
		//For each of these, we need to fetch the user's mailaddress and send her/him a mail.
		echo "Found new expired message " .$value["id"];
		$user = get_user_maildetails($value["id_user"]);
		$username = $user["name"];
		$extend_url = $base_url . '/userdetails/mymsg_extend.php?id=' . $value['id'] . '&validity=';
		$va = ($value['msg_type']) ? 'aanbod' : 'vraag';
		$content = "Beste $username\n\nJe " . $va . ' ' . $value["content"];
		$content .= " in eLAS is vervallen en zal over " . $msgcleanupdays . ' dagen verwijderd worden. ';
		$content .= "Om dit te voorkomen kan je inloggen op eLAS en onder de optie 'Mijn Vraag & Aanbod' voor verlengen kiezen. ";
		$content .= "\n Verlengen met één maand: " . $extend_url . "1 \n";
		$content .= "\n Verlengen met één jaar: " . $extend_url . "12 \n";
		$content .= "\n Verlengen met vijf jaar: " . $extend_url . "60 \n";
		$content .= "\n Nieuw vraag of aanbod ingeven: " . $base_url . "/messages/edit.php?mode=new \n";

		$mailto = $user["emailaddress"];

		$subject = 'Je ' . $va . ' in eLAS is vervallen.';

		$from_address_transactions = readconfigfromdb("from_address_transactions");

		if (!empty($from_address_transactions))
		{
			$mailfrom = trim($from_address_transactions);
		}
		else
		{
			echo "Mail from address is not set in configuration\n";
			return;
		}

		$systemtag = readconfigfromdb("systemtag");
		$subject = "[eLAS-".$systemtag ."] - " . $subject;

		$mailcontent = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n\n";
		$mailcontent .= "$content\n\n";

		$mailcontent .= "Als je nog vragen of problemen hebt, kan je mailen naar ";
		$mailcontent .= readconfigfromdb("support");

		$mailcontent .= "\n\nDe eLAS Robot\n";
		sendemail($mailfrom, $mailto, $subject, $mailcontent);
		log_event("","Mail","Message expiration mail sent to $mailto");
	}

	$db->Execute('UPDATE messages set exp_user_warn = \'t\' WHERE validity < \'' . $now . '\'');

	//no double warn in eLAS-Heroku.

	return true;
}

run_cronjob('cleanup_messages', 86400);

function cleanup_messages()
{
	global $db, $now, $s3;

	$msgs = '';
	$testdate = gmdate('Y-m-d H:i:s', time() - readconfigfromdb('msgexpcleanupdays') * 86400);
	$rs = $db->Execute("SELECT id, content, id_category, msg_type
		FROM messages
		WHERE validity < '" .$testdate ."'");
	while ($row = $rs->FetchRow())
	{
		$msgs .= $row['id'] . ': ' . $row['content'] . ', ';
	}
	$msgs = trim($msgs, '\n\r\t ,;:');

	if ($msgs)
	{
		log_event('','Cron','Expired and deleted Messages ' . $msgs);

		$db->Execute('DELETE FROM messages WHERE validity < \'' . $testdate . '\'');
	}

	$users = '';
	$ids = array();
	$rs = $db->Execute("SELECT u.id, u.letscode, u.name
		FROM users u, messages m
		WHERE u.status = 0
			AND m.id_user = u.id");

	while ($row = $rs->FetchRow())
	{
		$ids[] = $row['id'];
		$users .= '(id: ' . $row['id'] . ') ' . $row['letscode'] . ' ' . $row['name'] . ', ';
	}
	$users = trim($users, '\n\r\t ,;:');

	if (count($ids))
	{
		log_event('','Cron','Cleanup messages from users: ' . $users);
		echo 'Cleanup messages from users: ' . $users;

		if (count($ids) == 1)
		{
			$db->Execute('delete from messages where id_user = ' . $ids[0]);
		}
		else if (count($ids) > 1)
		{
			$db->Execute('delete from messages where id_user in ('. implode(', ', $ids) . ')');
		}
	}

	// remove orphaned images.
	$orphan_images = $db->GetAssoc('SELECT mp.id, mp."PictureFile"
		FROM msgpictures mp
		LEFT JOIN messages m ON mp.msgid = m.id
		WHERE m.id IS NULL');

	if (count($orphan_images))
	{
		foreach ($orphan_images as $id => $file)
		{
			$result = $s3->deleteObject(array(
				'Bucket' => getenv('S3_BUCKET'),
				'Key'    => $file,
			));

			$db->Execute('DELETE FROM msgpictures WHERE id = ' . $id);
		}
	}

	// update counts for each category

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

		$want_count[$id] = (isset($want_count[$id])) ? $want_count[$id] : 0;
		$offer_count[$id] = (isset($offer_count[$id])) ? $offer_count[$id] : 0;

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

run_cronjob('saldo_update', 86400); 

function saldo_update()
{
	global $db, $r;

	$user_balances = $db->GetAssoc('select id, saldo from users');

	$min = $db->GetAssoc('select id_from, sum(amount)
		from transactions
		group by id_from');
	$plus = $db->GetAssoc('select id_to, sum(amount)
		from transactions
		group by id_to');

	foreach ($user_balances as $id => $balance)
	{
		$plus[$id] = (isset($plus[$id])) ? $plus[$id] : 0;
		$min[$id] = (isset($min[$id])) ? $min[$id] : 0;

		$calculated = $plus[$id] - $min[$id];

		if ($balance == $calculated)
		{
			continue;
		}

		$db->Execute('update users set saldo = ' . $calculated . ' where id = ' . $id);
		$m = 'User id ' . $id . ' balance updated, old: ' . $balance . ', new: ' . $calculated;
		echo $m . $r;
		log_event('', 'Cron' , $m);
	}

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

run_cronjob('cleanup_logs', 86400);

function cleanup_logs()
{
	global $elas_log;
	$elas_log->cleanup();
	return true;
}

echo "*** Cron run finished ***" . $r;
exit;

////////////////////

function run_cronjob($name, $interval = 300, $enabled = null)
{
	global $db, $r, $now;
	static $lastrun_ary;

	if (!(isset($lastrun_ary) && is_array($lastrun_ary)))
	{
		$lastrun_ary = $db->GetAssoc('select cronjob, lastrun from cron');
	}

	if (!((time() - $interval > ((isset($lastrun_ary[$name])) ? strtotime($lastrun_ary[$name]) : 0)) & ($enabled || !isset($enabled))))
	{
		echo '+++ Cronjob: ' . $name . ' not running. +++' . $r;
		return;
	}

	echo '+++ Running ' . $name . ' +++' . $r;

	$updated = call_user_func($name);

	if (isset($lastrun_ary[$name]))
	{
		$db->Execute('update cron set lastrun = \'' . gmdate('Y-m-d H:i:s') . '\' where cronjob = \'' . $name . '\'');
	}
	else
	{
		$db->Execute('insert into cron (cronjob, lastrun) values (\'' . $name . '\', \'' . $now . '\')');
	}
	log_event(0, 'cron', 'Cronjob ' . $name . ' finished.');
	echo '+++ Cronjob ' . $name . ' finished. +++' . $r;

	return $updated;
}
