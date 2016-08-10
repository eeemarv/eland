<?php

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
$page_access = 'anonymous';
$allow_session = true;
require_once $rootpath . 'includes/inc_default.php';

require_once $rootpath . 'includes/inc_saldo_mail.php';
require_once $rootpath . 'includes/inc_mail.php';

$s3 = Aws\S3\S3Client::factory([
	'signature'	=> 'v4',
	'region'	=> 'eu-central-1',
	'version'	=> '2006-03-01',
]);

header('Content-Type:text/html');
echo '*** Cron eLAND ***' . $r;

echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r;

/*
 *  select in which schema to perform updates
 */

$schema_lastrun_ary = [];

foreach ($schemas as $ho => $sch)
{
	$lastrun = $app['db']->fetchColumn('select max(lastrun) from ' . $sch . '.cron');
	$schema_lastrun_ary[$sch] = ($lastrun) ?: 0;
}

unset($sch, $ho, $selected);

if (count($schemas))
{
	asort($schema_lastrun_ary);

	echo 'Schema (domain): last cron timestamp : interletsqueue timestamp' . $r;
	echo '---------------------------------------------------------------' . $r;

	foreach ($schema_lastrun_ary as $sch => $time)
	{
		echo $sch . ' (' . $hosts[$sch] . '): ' . $time;

		if (!isset($selected))
		{
			$schema = $sch;
			echo ' (selected)';
			$app['db']->exec('SET search_path TO ' . $schema);
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

$systemname = readconfigfromdb('systemname');
$systemtag = readconfigfromdb('systemtag');
$currency = readconfigfromdb('currency');
$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

echo "*** Cron system running [" . $schema . ' ' . $hosts[$schema] . ' ' . $systemtag ."] ***" . $r;

$base_url = $app_protocol . $hosts[$schema]; 

/**
 * typeahead && msgs from eLAS interlets update
 */

$update_msgs = false;

$groups = $app['db']->fetchAll('select *
	from letsgroups
	where apimethod = \'elassoap\'
		and remoteapikey IS NOT NULL
		and url <> \'\'');

foreach ($groups as $group)
{
	$group['domain'] = get_host($group);

	if (isset($schemas[$group['domain']]))
	{
		unset($group);
		continue;
	}

	if ($app['redis']->get($schema . '_token_failed_' . $group['remoteapikey'])
		|| $app['redis']->get($schema . '_connection_failed_' . $group['domain']))
	{
		unset($group);
		continue;
	}

	if (!$app['redis']->get($group['domain'] . '_typeahead_updated'))
	{
		break;
	}
/*
	if (!$app['redis']->get($group['domain'] . '_msgs_updated'))
	{
		$update_msgs = true;
		break;
	}
*/
	unset($group);
}

if (isset($group))
{
	$err_group = $group['groupname'] . ': ';

	$soapurl = ($group['elassoapurl']) ? $group['elassoapurl'] : $group['url'] . '/soap';
	$soapurl = $soapurl . '/wsdlelas.php?wsdl';
	$apikey = $group['remoteapikey'];
	$client = new nusoap_client($soapurl, true);
	$err = $client->getError();

	if ($err)
	{

		echo $err_group . 'Can not get connection.' . $r;
		$redis_key = $schema . '_connection_failed_' . $group['domain'];
		$app['redis']->set($redis_key, '1');
		$app['redis']->expire($redis_key, 21600);  // 6 hours

	}
	else
	{

		$token = $client->call('gettoken', ['apikey' => $apikey]);
		$err = $client->getError();

		if ($err)
		{
			echo $err_group . 'Can not get token.' . $r;
		}
		else if (!$token || $token == '---')
		{
			$err = 'invalid token';
			echo $err_group . 'Invalid token.' . $r;
		}

		if ($err)
		{
			$redis_key = $schema . '_token_failed_' . $group['remoteapikey'];
			$app['redis']->set($redis_key, '1');
			$app['redis']->expire($redis_key, 21600);  // 6 hours
		}
	}

	if (!$err)
	{
		try
		{
			$client = new Goutte\Client();

			$crawler = $client->request('GET', $group['url'] . '/login.php?token=' . $token);

			require_once $rootpath . 'includes/inc_interlets_fetch.php';

			if ($update_msgs)
			{
				echo 'fetch interlets messages' . $r;
				fetch_interlets_msgs($client, $group);
			}
			else
			{
				echo 'fetch interlets typeahead data' . $r;
				fetch_interlets_typeahead_data($client, $group);
			}

			echo '----------------------------------------------------' . $r;
			echo 'end Cron ' . "\n";
			exit;
		}
		catch (Exception $e)
		{
			$err = $e->getMessage();
			echo $err . $r;
			$redis_key = $schema . '_token_failed_' . $group['remoteapikey'];
			$app['redis']->set($redis_key, '1');
			$app['redis']->expire($redis_key, 21600);  // 6 hours

		}
	}

	if ($err)
	{
		echo '-- retry after 6 hours --' . $r;
		echo '-- continue --' . $r;
	}
}
else
{
	echo '-- no interlets data fetch needed -- ' . $r;
}

/*
 * Process autominlimits
 */

$autominlimit_queue = $app['eland.queue']->get('autominlimit', 6);

if (count($autominlimit_queue))
{
	echo '-- processing autominlimit queue -- ' . $r;

	foreach ($autominlimit_queue as $q)
	{
		$to_id = $q['to_id'];
		$from_id = $q['from_id'];
		$amount = $q['amount'];
		$sch = $q['schema'];

		if (!$to_id || !$from_id || !$amount || !$sch)
		{
			error_log('autominlimit 1');
			continue;
		}

		$user = readuser($to_id, false, $sch);
		$from_user = readuser($from_id, false, $sch);

		if (!$user
			|| !$amount
			|| !is_array($user)
			|| !in_array($user['status'], [1, 2])
			|| !$from_user
			|| !is_array($from_user)
			|| !$from_user['letscode']
		)
		{
			error_log('autominlimit 2');
			continue;
		}

		$row = $exdb->get('setting', 'autominlimit', $sch);

		$a = $row['data'];

		$new_user_time_treshold = time() - readconfigfromdb('newuserdays', $sch) * 86400;

		$user['status'] = ($new_user_time_treshold < strtotime($user['adate'] && $user['status'] == 1)) ? 3 : $user['status'];

		$inclusive = explode(',', $a['inclusive']);
		$exclusive = explode(',', $a['exclusive']);
		$trans_exclusive = explode(',', $a['trans_exclusive']);

		array_walk($inclusive, function(&$val){ return strtolower(trim($val)); });	
		array_walk($exclusive, function(&$val){ return strtolower(trim($val)); });
		array_walk($trans_exclusive, function(&$val){ return strtolower(trim($val)); });

		$inclusive = array_fill_keys($inclusive, true);
		$exclusive = array_fill_keys($exclusive, true);
		$trans_exclusive = array_fill_keys($trans_exclusive, true);

		$inc = (isset($inclusive[strtolower($user['letscode'])])) ? true :false; 

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
			echo 'auto_minlimit: no new minlimit for user ' . link_user($user, $sch, false) . $r;
			continue;
		}

		$extract = round(($a['trans_percentage'] / 100) * $amount);

		if (!$extract)
		{
			continue;
		}

		$new_minlimit = $user['minlimit'] - $extract;
		$new_minlimit = ($new_minlimit < $a['min']) ? $a['min'] : $new_minlimit;

		$exdb->set('autominlimit', $to_id, ['minlimit' => $new_minlimit], $sch);

		$app['db']->update($sch . '.users', ['minlimit' => $new_minlimit], ['id' => $to_id]);
		readuser($to_id, true, $sch);

		echo 'new minlimit ' . $new_minlimit . ' for user ' . link_user($user, $sch, false) .  $r;

		log_event('cron', 'autominlimit: new minlimit : ' . $new_minlimit . ' for user ' . link_user($user, $sch, false) . ' (id:' . $to_id . ') ', $sch);
	}

	echo '--- end queue autominlimit --- ' . $r;
}
else
{
	echo '-- autominlimit queue is empty --' . $r;
}

/**
 * Geocoding queue
 */ 

$log_ary = [];

$st = $app['db']->prepare('select c.value, c.id_user
	from contact c, type_contact tc, users u
	where c.id_type_contact = tc.id
		and tc.abbrev = \'adr\'
		and c.id_user = u.id
		and u.status in (1, 2)');

$st->execute();

while ($row = $st->fetch())
{
	$adr = $row['value'];

	$key = 'geo_' . $adr;

	if ($app['redis']->exists($key))
	{
		continue;
	}

	$data = [
		'adr'	=> $adr,
		'uid'	=> $row['id_user'],
		'sch'	=> $schema,
	];

	$app['redis']->set($key, 'q');
	$app['redis']->expire($key, 2592000);

	$app['eland.queue']->set('geo', $data);

	//$app['redis']->lpush('geo_q', json_encode($data));

	$log_ary[] = link_user($row['id_user'], false, false, true) . ': ' . $adr;
}

if (count($log_ary))
{
	log_event('cron geocode', 'Adresses queued for geocoding: ' . implode(', ', $log_ary));
}

// end queue addresses to geocode queue

run_cronjob('geo_q_process', 600);

function geo_q_process()
{
	global $app, $r;

	if ($app['redis']->exists('geo_sleep'))
	{
		echo 'geocoding sleep';
		return true;
	}

	$curl = new \Ivory\HttpAdapter\CurlHttpAdapter();
	$geocoder = new \Geocoder\ProviderAggregator();

	$geocoder->registerProviders([
		new \Geocoder\Provider\GoogleMaps(
			$curl, 'nl', 'be', true
		),
	]);

	$geocoder->using('google_maps')
		->limit(1);

	$rows = $app['eland.queue']->get('geo', 4);

	foreach ($rows as $data)
	{
		$adr = $data['adr'];
		$uid = $data['uid'];
		$sch = $data['sch'];

		$user = readuser($uid, false, $sch);

		$log_user = ' user: ' . $sch . '.' . $user['letscode'] . ' ' . $user['name'] . ' (' . $uid . ')';

		$key = 'geo_' . $adr;

		$status = $app['redis']->get($key);

		if ($status != 'q' && $status != 'f')
		{
			continue;
		}

		try
		{
			$address_collection = $geocoder->geocode($adr);

			if (is_object($address_collection))
			{
				$address = $address_collection->first();

				$ary = [
					'lat'	=> $address->getLatitude(),
					'lng'	=> $address->getLongitude(),
				];

				$app['redis']->set($key, json_encode($ary));
				$app['redis']->expire($key, 31536000); // 1 year
				$log = 'Geocoded: ' . $adr . ' : ' . implode('|', $ary);
				echo  $log . $r;
				log_event('cron geocode', $log . $log_user, $sch);
				continue;
			}

			$log_1 = 'Geocode return NULL for: ' . $adr;

		}

		catch (Exception $e)
		{
			$log = 'Geocode adr: ' . $adr . ' exception: ' . $e->getMessage();
		}

		echo  $log . $r;
		log_event('cron geocode', $log . $log_user, $sch);
		$app['redis']->set($key, 'f');
		$app['redis']->expire($key, 31536000); // 1 year

		$app['redis']->set('geo_sleep', '1');
		$app['redis']->expire('geo_sleep', 3600);
		break;
	}

	return true;
}

/**
 * Send emails
 */

run_cronjob('sendmail', 50);

/**
 * Periodic overview mail
 */

run_cronjob('saldo', 86400 * readconfigfromdb('saldofreqdays'));

/**
 * Report expired messages to the admin by mail
 */

run_cronjob('admin_exp_msg', 86400 * readconfigfromdb('adminmsgexpfreqdays'), readconfigfromdb('adminmsgexp'));

function admin_exp_msg()
{
	global $app, $now, $r, $base_url, $systemtag;

	$query = 'SELECT m.id_user, m.content, m.id, to_char(m.validity, \'YYYY-MM-DD\') as vali
		FROM messages m, users u
		WHERE u.status <> 0
			AND m.id_user = u.id
			AND validity <= ?';
	$messages = $app['db']->fetchAll($query, [$now]);

	if (empty($to))
	{
		echo 'No admin E-mail address specified in config' . $r;
		return false;
	}

	$subject = 'Rapport vervallen Vraag en aanbod';

	$text = "-- Dit is een automatische mail, niet beantwoorden aub --\n\n";
	$text .= "Gebruiker\t\tVervallen vraag of aanbod\t\tVervallen\n\n";
	
	foreach($messages as $key => $value)
	{
		$text .= link_user($value['id_user'], false, false) . "\t\t" . $value['content'] . "\t\t" . $value['vali'] ."\n";
		$text .= $base_url . '/messages.php?id=' . $value['id'] . " \n\n";
	}

	mail_q(['to' => 'admin', 'subject' => $subject, 'text' => $text]);

	return true;
}

/**
 * Notify users of expired messages
 */

run_cronjob('user_exp_msgs', 86400, readconfigfromdb('msgexpwarnenabled'));

function user_exp_msgs()
{
	global $app, $now, $base_url, $systemtag;

	//Fetch a list of all non-expired messages that havent sent a notification out yet and mail the user
	$msgcleanupdays = readconfigfromdb('msgexpcleanupdays');
	$warn_messages  = $app['db']->fetchAll('SELECT m.*
		FROM messages m
			WHERE m.exp_user_warn = \'f\'
				AND m.validity < ?', [$now]);

	foreach ($warn_messages AS $key => $value)
	{
		//For each of these, we need to fetch the user's mailaddress and send her/him a mail.
		echo 'Found new expired message ' . $value['id'];
		$user = readuser($value['id_user']);

		$extend_url = $base_url . '/messages.php?id=' . $value['id'] . '&extend=';
		$va = ($value['msg_type']) ? 'aanbod' : 'vraag';
		$text = "-- Dit is een automatische mail, niet beantwoorden aub --\r\n\r\n";
		$text .= "Beste " . $user['name'] . "\n\nJe " . $va . ' ' . $value['content'] . ' ';
		$text .= 'is vervallen en zal over ' . $msgcleanupdays . ' dagen verwijderd worden. ';
		$text .= 'Om dit te voorkomen kan je verlengen met behulp van één van de onderstaande links (Als ';
		$text .= 'je niet ingelogd bent, zal je eerst gevraagd worden in te loggen). ';
		$text .= "\n\n Verlengen met \n\n";
		$text .= "één maand: " . $extend_url . "30 \n";
		$text .= "twee maanden: " . $extend_url . "60 \n";
		$text .= "zes maanden: " . $extend_url . "180 \n";
		$text .= "één jaar: " . $extend_url . "365 \n";
		$text .= "twee jaar: " . $extend_url . "730 \n";
		$text .= "vijf jaar: " . $extend_url . "1825 \n\n";
		$text .= "Nieuw vraag of aanbod ingeven: " . $base_url . "/messages.php?add=1 \n\n";
		$text .= "Als je nog vragen of problemen hebt, kan je mailen naar ";
		$text .= readconfigfromdb('support');

		$subject = 'Je ' . $va . ' is vervallen.';

		if (empty($from))
		{
			echo "Mail from address is not set in configuration\n";
			return;
		}

		mail_q(['to' => $value['id_user'], 'subject' => $subject, 'text' => $text]);

		log_event('mail', 'Message expiration mail sent to ' . $to);
	}

	$app['db']->executeUpdate('update messages set exp_user_warn = \'t\' WHERE validity < ?', [$now]);

	//no double warn in eLAND.

	return true;
}

/**
 * Cleanup messages
 */

run_cronjob('cleanup_messages', 86400);

function cleanup_messages()
{
	global $app, $now;

	$msgs = '';
	$testdate = gmdate('Y-m-d H:i:s', time() - readconfigfromdb('msgexpcleanupdays') * 86400);

	$st = $app['db']->prepare('SELECT id, content, id_category, msg_type
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
		log_event('cron','Expired and deleted Messages ' . $msgs);

		$app['db']->executeQuery('delete from messages WHERE validity < ?', [$testdate]);
	}

	$users = '';
	$ids = [];

	$st = $app['db']->prepare('SELECT u.id, u.letscode, u.name
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
		log_event('cron','Cleanup messages from users: ' . $users);
		echo 'Cleanup messages from users: ' . $users;

		if (count($ids) == 1)
		{
			$app['db']->delete('messages', ['id_user' => $ids[0]]);
		}
		else if (count($ids) > 1)
		{
			$app['db']->executeQuery('delete from messages where id_user in (?)',
				[$ids],
				[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
		}
	}

	// remove orphaned images.
	$rs = $app['db']->prepare('SELECT mp.id, mp."PictureFile"
		FROM msgpictures mp
		LEFT JOIN messages m ON mp.msgid = m.id
		WHERE m.id IS NULL');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$app['db']->delete('msgpictures', ['id' => $row['id']]);
	}

	// update counts for each category

	$offer_count = $want_count = [];

	$rs = $app['db']->prepare('select m.id_category, count(m.*)
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

	$rs = $app['db']->prepare('select m.id_category, count(m.*)
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

	$all_cat = $app['db']->fetchAll('select id, stat_msgs_offers, stat_msgs_wanted
		from categories
		where id_parent is not null');

	foreach ($all_cat as $val)
	{
		$offers = $val['stat_msgs_offers'];
		$wants = $val['stat_msgs_wanted'];
		$id = $val['id'];

		$want_count[$id] = $want_count[$id] ?? 0;
		$offer_count[$id] = $offer_count[$id] ?? 0;

		if ($want_count[$id] == $wants && $offer_count[$id] == $offers)
		{
			continue;
		}

		$stats = [
			'stat_msgs_offers'	=> $offer_count[$id] ?? 0,
			'stat_msgs_wanted'	=> $want_count[$id] ?? 0,
		];
		
		$app['db']->update('categories', $stats, ['id' => $id]);
	}

	return true;
}

/**
 * Resyncronize balances
 */

run_cronjob('saldo_update', 86400); 

function saldo_update()
{
	global $app, $r;

	$user_balances = $min = $plus = [];

	$rs = $app['db']->prepare('select id, saldo from users');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$user_balances[$row['id']] = $row['saldo'];
	}

	$rs = $app['db']->prepare('select id_from, sum(amount)
		from transactions
		group by id_from');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$min[$row['id_from']] = $row['sum'];
	}

	$rs = $app['db']->prepare('select id_to, sum(amount)
		from transactions
		group by id_to');

	$rs->execute();

	while ($row = $rs->fetch())
	{
		$plus[$row['id_to']] = $row['sum'];
	}

	foreach ($user_balances as $id => $balance)
	{
		$plus[$id] = $plus[$id] ?? 0;
		$min[$id] = $min[$id] ?? 0;

		$calculated = $plus[$id] - $min[$id];

		if ($balance == $calculated)
		{
			continue;
		}

		$app['db']->update('users', ['saldo' => $calculated], ['id' => $id]);
		$m = 'User id ' . $id . ' balance updated, old: ' . $balance . ', new: ' . $calculated;
		echo $m . $r;
		log_event('cron' , $m);
	}

	return true;
}

/**
 * remove expired newsitems
 */

run_cronjob('cleanup_news', 86400);

function cleanup_news()
{
    global $app, $now, $exdb;

	$news = $app['db']->fetchAll('select id from news where itemdate < ? and sticky = \'f\'', [$now]);

	foreach ($news as $n)
	{
		$exdb->del('news_access', $n['id']);
		$app['db']->delete('news', ['id' => $n['id']]);
	}

	return true;
}

/**
 *
 */

run_cronjob('cleanup_tokens', 604800);

// tokens are stored in redis now, not anymore in db (cleaning up for new groups)

function cleanup_tokens()
{
	global $app, $now;

	$app['db']->executeQuery('delete from tokens where validity < ?', [$now]) ? true : false;
	return true;
}

/**
 * cleanup image files // schema-independent cronjob.
 * images are deleted from s3 170 days after been deleted from db.
 */

if (!$app['redis']->get('cron_cleanup_image_files'))
{
	echo '+++ cleanup image files +++' . $r;

	$marker = $app['redis']->get('cleanup_image_files_marker');

	$marker = ($marker) ? $marker : '0';

	$time_treshold = time() - (84600 * 170);

	$del_count = 0;

	try {
		$objects = $s3->getIterator('ListObjects', [
			'Bucket'	=> $app['eland.s3_img'],
			'Marker'	=> $marker,
		]);

		//echo $r . 'Keys retrieved' . $r;

		$reset = true;

		foreach ($objects as $k => $object)
		{
			if ($k > 4)
			{
				$reset = false;
				break;
			}

			$delete = $del_str = false;

			$object_time = strtotime($object['LastModified']);

			$old = ($object_time < $time_treshold) ? true : false;

			$str_log = $k . ' ' .  $object['Key'] . ' ' . $object['LastModified'] . ' ';

			$str_log .= ($old) ? 'OLD' : 'NEW';

			error_log($str_log);

			if (!$old)
			{
				continue;
			}

			list($sch, $type, $id, $hash) = explode('_', $object['Key']);

			if (ctype_digit((string) $sch))
			{
				error_log('-> elas import image. DELETE');
				$delete = true;
			} 


			if (!$delete && !isset($hosts[$sch]))
			{
				error_log('-> unknown schema');
				continue;
			}

			if (!$delete && !in_array($type, ['u', 'm']))
			{
				error_log('-> unknown type');
				continue;
			}

			if (!$delete && $type == 'u' && ctype_digit((string) $id))
			{
				$user = $app['db']->fetchAssoc('select id, "PictureFile" from ' . $sch . '.users where id = ?', [$id]);

				if (!$user)
				{
					$del_str = '->User does not exist.';
					$delete = true;
				}
				else if ($user['PictureFile'] != $object['Key'])
				{
					$del_str = '->does not match db key ' . $user['PictureFile'];
					$delete = true;
				}
			}

			if (!$delete && $type == 'm' && ctype_digit((string) $id))
			{
				$msgpict = $app['db']->fetchAssoc('select * from ' . $sch . '.msgpictures
					where msgid = ?
						and "PictureFile" = ?', [$id, $object['Key']]);

				if (!$msgpict)
				{
					$del_str = '->is not present in db.';
					$delete = true;
				}
			}

			if (!$delete)
			{
				continue;
			}

			$s3->deleteObject([
				'Bucket'	=> $app['eland.s3_img'],
				'Key'		=> $object['Key'],
			]);

			if ($del_str)
			{
				log_event('cron', 'image file ' . $object['Key'] . ' deleted ' . $del_str, $sch);
			}

			$del_count++;
		}
	}
	catch (S3Exception $e)
	{
		echo $e->getMessage() . $r . $r;
	}

	$app['redis']->set('cleanup_image_files_marker', $reset ? '0' : $object['Key']);
	$app['redis']->set('cron_cleanup_image_files', '1');
	$app['redis']->expire('cron_cleanup_image_files', 900);

	echo  $del_count . ' images deleted.' . $r;

	echo '+++ end image files cleanup +++' . $r . $r;
}
else
{
	echo '+++ cleanup image files not running +++' . $r;
}


/**
 * Cleanup old logs
 */

run_cronjob('cleanup_logs', 86400);

function cleanup_logs()
{
	global $app, $schema;

	$treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 30);

	$app['db']->executeQuery('delete from eland_extra.logs
		where schema = ? and ts < ?', [$schema, $treshold]);

	log_event('cron', 'Cleaned up logs older than 30 days.');

	return true;
}

/**
 * Dummy cronjob in order to keep rotating groups for cronjobs.
 */

run_cronjob('cronschedule', 10);

function cronschedule()
{
	return true;
}

echo "*** Cron run finished ***" . $r;
exit;

////////////////////

function run_cronjob($name, $interval = 300, $enabled = null)
{
	global $app, $r, $now;
	static $lastrun_ary;

	if (!(isset($lastrun_ary) && is_array($lastrun_ary)))
	{
		$lastrun_ary = [];

		$rs = $app['db']->prepare('select cronjob, lastrun from cron');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$lastrun_ary[$row['cronjob']] = $row['lastrun'];
		}
	}

	$time = time();
	$lastrun = (isset($lastrun_ary[$name])) ? strtotime($lastrun_ary[$name] . ' UTC') : 0;

	if (!((($time - $interval) > $lastrun) & ($enabled || !isset($enabled))))
	{
		echo '+++ Cronjob: ' . $name . ' not running. +++' . $r;
		return;
	}

	echo '+++ Running ' . $name . ' +++' . $r;

	$updated = call_user_func($name);

	$lastrun = ((($time - ($lastrun + $interval)) > 86400) || ($interval < 86401)) ? $time : $lastrun + $interval;

	if (isset($lastrun_ary[$name]))
	{
		$app['db']->update('cron', ['lastrun' => gmdate('Y-m-d H:i:s', $lastrun)], ['cronjob' => $name]);
	}
	else
	{
		$app['db']->insert('cron', ['cronjob' => $name, 'lastrun'	=> $now]);
	}

	echo '+++ Cronjob ' . $name . ' finished. +++' . $r;

	return $updated;
}
