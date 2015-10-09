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

$rootpath = './';
$role = 'anonymous';
require_once $rootpath . 'includes/inc_default.php';

require_once $rootpath . 'includes/inc_processqueue.php';
require_once $rootpath . 'includes/inc_saldo_mail.php';

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

$schemas_db = ($db->fetchAll('select schema_name from information_schema.schemata')) ?: array();
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

	$lastrun = $db->fetchColumn('select max(lastrun) from ' . $schema . '.cron');
	$schema_lastrun_ary[$schema] = ($lastrun) ?: 0;

	if ($date_interletsq = $db->fetchColumn('select min(date_created)
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
			$db->exec('SET search_path TO ' . $schema);
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

$elas_mongo->set_schema($schema);

$base_url = $http . $domains[$schema];

// begin typeahaed update (when interletsq is empty) for one group

if (!isset($schema_interletsq_min))
{

	$letsgroups = $db->fetchAll('SELECT *
		FROM letsgroups
		WHERE apimethod = \'elassoap\'
			AND remoteapikey IS NOT NULL');

	foreach ($letsgroups as $letsgroup)
	{
		if ($redis->get($schema . '_typeahead_failed_' . $letsgroup['remoteapikey'])
			|| $redis->get($schema . '_typeahead_failed_' . $letsgroup['url']))
		{
			unset($letsgroup);
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
		$soapurl = $soapurl . '/wsdlelas.php?wsdl';
		$apikey = $letsgroup['remoteapikey'];
		$client = new nusoap_client($soapurl, true);
		$err = $client->getError();
		if ($err)
		{
			echo $err_group . 'Can not get connection.' . $r;

			$redis_key = $schema . '_typeahead_failed_' . $letsgroup['url'];
			$redis->set($redis_key, '1');
			$redis->expire($redis_key, 21600);  // 6 hours
		}
		else
		{
			$token = $client->call('gettoken', array('apikey' => $apikey));
			$err = $client->getError();
			if ($err)
			{
				echo $err_group . 'Can not get token.' . $r;

				$unvalid_apikeys[$letsgroup['remoteapikey']] = 1;
				$redis_key = $schema . '_typeahead_failed_' . $letsgroup['remoteapikey'];
				$redis->set($redis_key, '1');
				$redis->expire($redis_key, 21600);  // 6 hours
			}
		}

		if (!$err)
		{	
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
			echo '-- continue --' . $r;
		}
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
			AND validity <= ?";
	$messages = $db->fetchAll($query, array($now));

	$admin = readconfigfromdb('admin');
	if (empty($admin))
	{
		echo "No admin E-mail address specified in config" . $r;
		return false;
	}
	else
	{
	   $to = $admin;
	}

	$from_address_transactions = readconfigfromdb('from_address_transactions');

	if (!empty($from_address_transactions))
	{
		$from .= "From: ".trim($from_address_transactions);
	}
	else
	{
		echo "Mail from address is not set in configuration" . $r;
		return 0;
	}

	$systemtag = readconfigfromdb("systemtag");
	$subject = "[eLAS-".$systemtag ."] - Rapport vervallen V/A";

	$content = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --" . $r;
	$content .= "ID\tUser\tMessage\n";
	
	foreach($messages as $key => $value)
	{
		$content .=  $value["mid"] ."\t" .$value["username"] ."\t" .$value["message"] ."\t" .$value["validity"] ."\n";
		$content .= $base_url . '\messages\view?id=' . $value['mid'] . " \n";
	}

	$content .=  $r;

	sendemail($from, $to, $subject, $content);

	return true;
}

run_cronjob('user_exp_msgs', 86400, readconfigfromdb('msgexpwarnenabled'));

function user_exp_msgs()
{
	global $db, $now, $base_url;
	//Fetch a list of all non-expired messages that havent sent a notification out yet and mail the user
	$msgcleanupdays = readconfigfromdb('msgexpcleanupdays');
	$warn_messages  = $db->fetchAll("SELECT m.*
		FROM messages m
			WHERE m.exp_user_warn = 'f'
				AND m.validity < ?", array($now));

	foreach ($warn_messages AS $key => $value)
	{
		//For each of these, we need to fetch the user's mailaddress and send her/him a mail.
		echo "Found new expired message " .$value['id'];
		$user = readuser($value['id_user']);
		$to = $db->fetchColumn('select c.value
			from contact c, type_contact tc
			where c.id_type_contact = tc.id
				and c.id_user = ?
				and tc.abbrev = \'mail\'', array($value['id_user']));
		$username = $user["name"];
		$extend_url = $base_url . '/userdetails/mymsg_extend.php?id=' . $value['id'] . '&validity=';
		$va = ($value['msg_type']) ? 'aanbod' : 'vraag';
		$content = "Beste " . $username . "\n\nJe " . $va . ' ' . $value['content'];
		$content .= " in eLAS is vervallen en zal over " . $msgcleanupdays . ' dagen verwijderd worden. ';
		$content .= "Om dit te voorkomen kan je inloggen op eLAS en onder de optie 'Mijn Vraag & Aanbod' voor verlengen kiezen. ";
		$content .= "\n Verlengen met één maand: " . $extend_url . "1 \n";
		$content .= "\n Verlengen met één jaar: " . $extend_url . "12 \n";
		$content .= "\n Verlengen met vijf jaar: " . $extend_url . "60 \n";
		$content .= "\n Nieuw vraag of aanbod ingeven: " . $base_url . "/messages/edit.php?mode=new \n";

		$subject = 'Je ' . $va . ' in eLAS is vervallen.';

		$from_address_transactions = readconfigfromdb("from_address_transactions");

		if (!empty($from_address_transactions))
		{
			$from = trim($from_address_transactions);
		}
		else
		{
			echo "Mail from address is not set in configuration\n";
			return;
		}

		$systemtag = readconfigfromdb("systemtag");
		$subject = "[eLAS-".$systemtag ."] - " . $subject;

		$content = "-- Dit is een automatische mail van het eLAS systeem, niet beantwoorden aub --\r\n\n";
		$content .= "$content\n\n";

		$content .= "Als je nog vragen of problemen hebt, kan je mailen naar ";
		$content .= readconfigfromdb('support');

		$content .= "\n\nDe eLAS Robot\n";
		sendemail($from, $to, $subject, $content);
		log_event('', 'Mail', 'Message expiration mail sent to ' . $to);
	}

	$db->executeUpdate('update messages set exp_user_warn = \'t\' WHERE validity < ?', array($now));

	//no double warn in eLAS-Heroku.

	return true;
}

run_cronjob('cleanup_messages', 86400);

function cleanup_messages()
{
	global $db, $now, $s3;

	$msgs = '';
	$testdate = gmdate('Y-m-d H:i:s', time() - readconfigfromdb('msgexpcleanupdays') * 86400);

	$st = $db->prepare('SELECT id, content, id_category, msg_type
		FROM messages
		WHERE validity < ?');

	$st->bindValue(1, $testdate);
	$st->execute();

	while ($row = $st->fetch())
	{
		$msgs .= $row['id'] . ': ' . $row['content'] . ', ';
	}
	$msgs = trim($msgs, '\n\r\t ,;:');

	if ($msgs)
	{
		log_event('','Cron','Expired and deleted Messages ' . $msgs);

		$db->executeQuery('delete from messages WHERE validity < ?', array($testdate));
	}

	$users = '';
	$ids = array();

	$st = $db->prepare('SELECT u.id, u.letscode, u.name
		FROM users u, messages m
		WHERE u.status = 0
			AND m.id_user = u.id');

	$st->execute();

	while ($row = $st->fetch())
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
			$db->delete('messages', array('id_user' => $ids[0]));
		}
		else if (count($ids) > 1)
		{
			$db->executeQuery('delete from messages where id_user in (?)',
				array($ids),
				array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
		}
	}

	// remove orphaned images.
	$rs = $db->prepare('SELECT mp.id, mp."PictureFile"
		FROM msgpictures mp
		LEFT JOIN messages m ON mp.msgid = m.id
		WHERE m.id IS NULL');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$result = $s3->deleteObject(array(
			'Bucket' => getenv('S3_BUCKET'),
			'Key'    => $row['PictureFile'],
		));

		$db->delete('msgpictures', array('id' => $row['id']));
	}

	// update counts for each category

	$offer_count = $want_count = array();

	$rs = $db->prepare('select m.id_category, count(m.*)
		from messages m, users u
		where  m.id_user = u.id
			and u.status IN (1, 2, 3)
			and msg_type = 1
		group by m.id_category');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$offer_count[$row['id_category']] = $row['count'];
	}

	$rs = $db->prepare('select m.id_category, count(m.*)
		from messages m, users u
		where  m.id_user = u.id
			and u.status IN (1, 2, 3)
			and msg_type = 0
		group by m.id_category');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$want_count[$row['id_category']] = $row['count'];
	}

	$all_cat = $db->fetchAll('select id, stat_msgs_offers, stat_msgs_wanted
		from categories
		where id_parent is not null');

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
		
		$db->update('categories', $stats, array('id' => $id));
	}

	return true;
}

run_cronjob('saldo_update', 86400); 

function saldo_update()
{
	global $db, $r;

	$user_balances = $min = $plus = array();

	$rs = $db->prepare('select id, saldo from users');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$user_balances[$row['id']] = $row['saldo'];
	}

	$rs = $db->prepare('select id_from, sum(amount)
		from transactions
		group by id_from');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$min[$row['id_from']] = $row['sum'];
	}

	$rs = $db->prepare('select id_to, sum(amount)
		from transactions
		group by id_to');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$plus[$row['id_to']] = $row['sum'];
	}

	foreach ($user_balances as $id => $balance)
	{
		$plus[$id] = (isset($plus[$id])) ? $plus[$id] : 0;
		$min[$id] = (isset($min[$id])) ? $min[$id] : 0;

		$calculated = $plus[$id] - $min[$id];

		if ($balance == $calculated)
		{
			continue;
		}

		$db->update('users', array('saldo' => $calculated), array('id' => $id));
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
	return ($db->executeQuery('delete from news where itemdate < ? and sticky = \'f\'', array($now))) ? true : false;
}

run_cronjob('cleanup_tokens', 3600);

function cleanup_tokens()
{
	global $db, $now;
	return ($db->executeQuery('delete from tokens where validity < ?', array($now))) ? true : false;
}

run_cronjob('cleanup_logs', 86400);

function cleanup_logs()
{
	global $elas_mongo;

	$elas_mongo->connect();	
	$treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 30);
	$elas_mongo->logs->remove(array('timestamp' => array('$lt' => $treshold)));
	return $this;
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
		$lastrun_ary = array();

		$rs = $db->prepare('select cronjob, lastrun from cron');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$lastrun_ary[$row['cronjob']] = $row['lastrun'];
		}
	}

	$time = time();
	$lastrun = (isset($lastrun_ary[$name])) ? strtotime($lastrun_ary[$name]) . ' UTC' : 0;

	if (!((($time - $interval) > $lastrun) & ($enabled || !isset($enabled))))
	{
		echo '+++ Cronjob: ' . $name . ' not running. +++' . $r;
		return;
	}

	echo '+++ Running ' . $name . ' +++' . $r;

	$updated = call_user_func($name);

	$lastrun = ($interval > 86400) ? $lastrun + $interval : $time;

	if (isset($lastrun_ary[$name]))
	{
		$db->update('cron', array('lastrun' => gmdate('Y-m-d H:i:s', $lastrun)), array('cronjob' => $name));
	}
	else
	{
		$db->insert('cron', array('cronjob' => $name, 'lastrun'	=> $now));
	}
	log_event(0, 'cron', 'Cronjob ' . $name . ' finished.');
	echo '+++ Cronjob ' . $name . ' finished. +++' . $r;

	return $updated;
}
