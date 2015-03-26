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

require_once $rootpath . 'vendor/autoload.php';
require_once $rootpath . 'includes/inc_redis.php';
require_once $rootpath . 'includes/inc_setstatus.php';
require_once $rootpath . 'includes/inc_timezone.php';
require_once $rootpath . 'includes/inc_version.php';
require_once $rootpath . 'cron/inc_cron.php';
require_once $rootpath . 'cron/inc_upgrade.php';

/**
require_once($rootpath."cron/inc_stats.php");
**/

require_once($rootpath."includes/inc_mailfunctions.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_saldofunctions.php");

require_once($rootpath."includes/inc_news.php");

require_once $rootpath . 'includes/inc_eventlog.php';
require_once $rootpath . 'includes/inc_dbconfig.php';

header('Content-Type:text/plain');

echo '*** Cron eLAS-Heroku ***' . $r . $r;
// echo 'version: ' . exec('git describe') . $r; (git not available)
echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r . $r;

$sessions = $domains = $session_cron_timestamps = $session_interletsqs = $table = array();

foreach ($_ENV as $key => $session_name)
{
	if (strpos($key, 'ELAS_DOMAIN_SESSION_') === 0
		&  isset($_ENV['HEROKU_POSTGRESQL_' . $session_name . '_URL']))
	{
		$domain = str_replace('ELAS_DOMAIN_SESSION_', '', $key);

		$sessions[$domain] = $session_name;

		$domain = str_replace('___', '-', $domain);
		$domain = str_replace('__', '.', $domain);
		$domain = strtolower($domain);

		$domains[$session_name] = $domain;

		$session_cron_timestamps[$session_name] = (int) $redis->get($session_name . '_cron_timestamp');

		if ($interletsq = (int) $redis->get($session_name . '_interletsq'))
		{
			$session_interletsqs[$session_name] = $interletsq;
		}
	}
}

unset($session_name, $domain);

if (count($sessions))
{
	asort($session_cron_timestamps);

	if (count($session_interletsqs))
	{
		list($session_interletsq_min) = array_keys($session_interletsqs, min($session_interletsqs));
	}

	echo 'Session name (domain): last cron timestamp : interletsqueue timestamp' . $r;
	echo '----------------------------------------------------------------------' . $r;
	foreach ($session_cron_timestamps as $session_n => $timestamp)
	{
		echo $session_n . ' (' . $domains[$session_n] . '): ' . $timestamp . ' : ';
		echo ($session_interletsqs[$session_n]) ? $session_interletsqs[$session_n] : 0;

		if ((!isset($db_url) && !isset($session_interletsq_min))
			|| isset($session_interletsq_min) && $session_interletsq_min == $session_n)
		{
			$db_url = $_ENV['HEROKU_POSTGRESQL_' . $session_n . '_URL'];
			$session_name = $session_n;
			echo ' (selected)';
		}
		echo $r;
	}
}
else
{
	$db_url = getenv('DATABASE_URL');

	if (!isset($db_url))
	{
		echo 'No database configured. Exit cron.' . $r;
		Exit;
	}

	foreach ($_ENV as $env => $value)
	{
		if ($db_url == $value && strpos('HEROKU_POSTGRESQL_', $env) === 0)
		{
			$session_name = str_replace('HEROKU_POSTGRESQL_', '', $env);
			$session_name = str_replace('_URL', '', $session_name);
			break;
		}
	}

	echo '-- No installed domains found. Select default database (' . $session_name . ') --';
}

echo $r . $r;

$db = NewADOConnection($db_url);

unset($db_url);

$db->SetFetchMode(ADODB_FETCH_ASSOC);

if(getenv('ELAS_DB_DEBUG')){
	$db->debug = true;
}

// Upgrade the DB first if required

$currentversion = $dbversion = $db->GetOne("SELECT * FROM parameters WHERE parameter = 'schemaversion'");
$doneversion = $currentversion;
if ($current_version < $schemaversion)
{
	echo '-- Database already up to date -- ' . $r;
}
else
{
	echo "eLAS database needs to upgrade from $currentversion to $schemaversion\n\n";
	while($currentversion < $schemaversion)
	{
		$currentversion++;
		if(doupgrade($currentversion) == TRUE){
			$doneversion = $currentversion;
		}
	}
	echo "Upgraded database from schema version $dbversion to $doneversion\n\n";
	log_event("","DB","Upgraded database from schema version $dbversion to $doneversion");	
}

echo $r;

echo "*** Cron system running [" . $session_name . ' ' . $domains[$session_name] . ' ' . readconfigfromdb('systemtag') ."] ***\n\n";

// begin typeahaed update (when interletsq is empty) for one group

if (!isset($session_interletsq_min))
{

	$letsgroups = $db->GetArray('SELECT *
		FROM letsgroups
		WHERE apimethod = \'elassoap\'
			AND remoteapikey IS NOT NULL');

	$letsgroups_typeahead_update = (json_decode($redis->get('letsgroups_typeahead_update'), true)) ?: array();

	$unvalid_apikeys = (json_decode($redis->get($session_name . '_typeahead_unvalid_apikeys'), true)) ?: array();

	$failed_connections = (json_decode($redis->get($session_name . '_typeahead_failed_connections'), true)) ?: array();

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
			$redis->set($session_name . '_typeahead_failed_connections', $failed_connections);
			$redis->expire($session_name . '_typeahead_failed_connections', 43200);  // 12 hours
		}
		else
		{
			$token = $client->call('gettoken', array('apikey' => $apikey));
			$err = $client->getError();
			if ($err)
			{
				echo $err_group . 'Kan geen token krijgen.' . $r;

				$unvalid_apikeys[$letsgroup['remoteapikey']] = 1;
				$redis->set($session_name . '_typeahead_unvalid_apikeys', $unvalid_apikeys);
				$redis->expire($session_name . '_typeahead_unvalid_apikeys',86400);  // 24 hours
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

		$redis->set($session_name . '_cron_timestamp', time());

		exit;
	}
	else
	{
		echo '-- no letsgroup typeahead update needed -- ' . $r;
	}
}
else
{
	echo '-- priority to no letsgroup typeahead updated --' . $r;
}

/// end typeahead update


// sync the image files  // (to do -- not in cron -- delete orphaned files in bucket)
if ((int) $redis->get($session_name . '_file_sync') < time() - 24 * 3600 * 30)
{
	$s3 = Aws\S3\S3Client::factory(array(
		'signature'	=> 'v4',
		'region'	=> 'eu-central-1',
	));

	echo 'Sync the image files.' . $r;

	$user_images = $db->GetAssoc('SELECT id, "PictureFile" FROM users WHERE "PictureFile" IS NOT NULL');

	foreach($user_images as $user_id => $filename)
	{
		list($f_session_name) = explode('_', $filename);

		if(!$s3->doesObjectExist(getenv('S3_BUCKET'), $filename))
		{
			$db->Execute('UPDATE users SET "PictureFile" = NULL WHERE id = ' . $user_id);
			echo '1 profile image not present, deleted in database. ' . $r;
			log_event ($s_id, 'cron', 'Profile image file of user ' . $user_id . ' was not available: deleted from database. Deleted filename : ' . $filename);
		}
		else if ($f_session_name != $session_name)
		{
			$new_filename = $session_name . '_u_' . $user_id . '_' . sha1(time() . $filename) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
			$result = $s3->copyObject(array(
				'Bucket'		=> getenv('S3_BUCKET'),
				'CopySource'	=> $filename,
				'Key'			=> $new_filename,
			));

			if ($result && $result instanceof \Guzzle\Service\Resource\Model)
			{
				$db->Execute('UPDATE users SET "PictureFile" = \'' . $new_filename . '\' WHERE id = ' . $user_id);
				echo '1 profile image renamed' . $r;
				log_event($s_id, 'cron', 'Profile image file renamed, Old: ' . $filename . ' New: ' . $new_filename);

				$s3->deleteObject(array(
					'Bucket'	=> getenv('S3_BUCKET'),
					'Key'		=> $filename,
				));
			}
		}
	}

	$message_images = $db->GetArray('SELECT id, msgid, "PictureFile" FROM msgpictures');

	foreach($message_images as $image)
	{
		$filename = $image['PictureFile'];
		$msg_id = $image['msgid'];
		$id = $image['id'];

		list($f_session_name) = explode('_', $filename);

		if(!$s3->doesObjectExist(getenv('S3_BUCKET'), $filename))
		{
			$db->Execute('DELETE FROM msgpictures WHERE id = ' . $id);
			echo '1 message image not present, deleted in database. ' . $r;
			log_event ($s_id, 'cron', 'Image file of message ' . $msg_id . ' was not available: deleted from database. Deleted : ' . $filename . ' id: ' . $id);
		}
		else if ($f_session_name != $session_name)
		{
			$new_filename = $session_name . '_m_' . $msg_id . '_' . sha1(time() . $filename) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
			$result = $s3->copyObject(array(
				'Bucket'		=> getenv('S3_BUCKET'),
				'CopySource'	=> $filename,
				'Key'			=> $new_filename,
			));

			if ($result && $result instanceof \Guzzle\Service\Resource\Model)
			{
				$db->Execute('UPDATE msgpictures SET "PictureFile" = \'' . $new_filename . '\' WHERE id = ' . $id);
				echo '1 profile image renamed' . $r;
				log_event($s_id, 'cron', 'Message image file renamed, Old : ' . $filename . ' New: ' . $new_filename);

				$s3->deleteObject(array(
					'Bucket'	=> getenv('S3_BUCKET'),
					'Key'		=> $filename,
				));
			}
		}
	}

	echo 'Sync image files ready.' . $r;
}

// end sync images

/*
// cleanup orphaned profile & message images by reading the S3 bucket every 30 days
*
* --> NOT IN CRON // move to command line
if ($redis->get($session_name . '_cleanup_profile_images_timestamp') < time() - 2592000)
{
	echo 'Run cleanup profile images' . $r;

	// get all objects
	$objects = $s3->getIterator('ListObjects', array(
		'Bucket' => getenv('S3_BUCKET')
	));

	foreach ($objects as $file)
	{
		list($sess, $type, $type_id, $hash) = explode('_', $file);
		
		if ($sess != $session_name)
		{
			continue;
		}

		if ($type == 'm')
		{
		* // not good
			//$db->getOne('SELECT 1 FROM msgpictures WHERE \'Picturefile\' = ' . $file);
		}
	}

	$redis->set($session_name . 'cleanup_profile_images_timestamp', time());
}
*/

$lastrun_ary = $db->GetAssoc('select cronjob, lastrun from cron');

// Auto mail saldo on request
$frequency = readconfigfromdb("saldofreqdays") * 1440;
if(check_timestamp($lastrun_ary['saldo'], $frequency) == 1)
{
	automail_saldo();
}

// Auto mail messages that have expired to the admin
$frequency = readconfigfromdb("adminmsgexpfreqdays") * 1440;
if(check_timestamp($lastrun_ary['admin_exp_msg'], $frequency) == 1 && readconfigfromdb("adminmsgexp") == 1)
{
	automail_admin_exp_msg();
}

// Check for and mail expired messages to the user
$frequency = 1440;
if(check_timestamp($lastrun_ary['user_exp_msgs'], $frequency) == 1 && readconfigfromdb("msgexpwarnenabled") == 1)
{
	check_user_exp_msgs();
}


// Clean up expired messages after the grace period
$frequency = 1440;
if(check_timestamp($lastrun_ary['cleanup_messages'], $frequency) == 1 && readconfigfromdb("msgcleanupenabled") == 1)
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
$frequency = 60;
if(check_timestamp($lastrun_ary['cat_update_count'], $frequency) == 1)
{
	cat_update_count();
}

// Update the cached saldo
$frequency = 60;
if(check_timestamp($lastrun_ary['saldo_update'], $frequency) == 1)
{
	saldo_update();
}

// Clean up expired news items
$frequency = 1440;
if(check_timestamp($lastrun_ary['cleanup_news'], $frequency) == 1)
{
        cleanup_news();
}

// Clean up expired tokens
$frequency = 1440;
if(check_timestamp($lastrun_ary['cleanup_tokens'], $frequency) == 1)
{
        cleanup_tokens();
}

// RUN the ILPQ
$frequency = 5;
if(check_timestamp($lastrun_ary['processqueue'], $frequency) == 1)
{
	require_once("$rootpath/interlets/processqueue.php");
	write_timestamp("processqueue");
}

// Publish news items that were approved
$frequency = 30;
if(check_timestamp($lastrun_ary['publish_news'], $frequency) == 1)
{
	publish_news();
}

// Update the stats table   Count total users / total transactions -> move this to Redis.
/**  
$frequency = 720;
if(check_timestamp("update_stats", $frequency) == 1){
	update_stats();
}
*
**/

// END


$redis->set($session_name . '_interletsq', '');
$redis->set($session_name . '_cron_timestamp', time());

echo "\nCron run finished\n";
exit;

////////////////////


function publish_mailinglists(){

	global $configuration;
	global $db;
	global $baseurl;
	echo "Running publish mailinglists to emessenger\n";
	echo "  lists\n";
	amq_publishmailinglists();
	echo "  subscribers\n";
	amq_publishsubcribers();
	write_timestamp("publish_mailinglists");
}

function process_amqmessages(){
	global $configuration;

	echo "Running Process AMQ messages...\n";
	echo "  Getting other AMQ messages...\n";
	amq_processincoming();
	write_timestamp("process_ampmessages");
}

function create_paths() {
	global $rootpath;
	global $baseurl;

	echo "Running create_paths...\n";

	// Auto create the json directory
	$dirname = "$rootpath/sites/$baseurl/json";
	if (!file_exists($dirname)){
		echo "    Creating directory $dirname\n";
		mkdir("$dirname", 0770);
		echo "    Creating .htaccess file for $dirname\n";
		file_put_contents("$dirname/.htaccess", "Deny from all\n");
	}

	write_timestamp("create_paths");
}

function mailq_run(){
	# FIXME Replace this code with direct connection to AMQ
	# Process mails in the queue and hand them of to a droid

	global $configuration;
	global $db;
	global $baseurl;

	$systemname = readconfigfromdb("systemname");
    $systemtag = readconfigfromdb("systemtag");

	echo "Running mailq_run...\n";

	$query = "SELECT * FROM mailq WHERE sent = 0";
	$mails = $db->GetArray($query);

	foreach ($mails AS $key => $value){
		echo "Processing message " .$value["msgid"] . " to list " .$value["listname"] . "\n";

		# Get all subscribers for that list
		$query = "SELECT * FROM lists, listsubscriptions WHERE listsubscriptions.listname = lists.listname AND listsubscriptions.listname = '" .$value["listname"] . "'";
		$subscribers = $db->GetArray($query);

		$footer = "--\nJe krijgt deze mail via de lijst '" .$value["listname"] ."' op de eLAS installatie van " .$systemname .".\nJe kan je mailinstellingen en abonnementen wijzigen in je eLAS profiel op http://" .$baseurl .".";
		// Set maildroid format version
		$message["mformat"] = "1";
		$message["id"] = $value["msgid"];
		$message["contenttype"] = "text/html";
		$message["charset"] = "utf-8";
		$message["from"] = $value["from"];
		$message["to"] = array();
		$message["subject"] = "[eLAS-$systemtag " . $value["listname"] ."] " .$value["subject"];
		$message["body"] = "<html>\n" .$value["message"];
		$message["body"] .= "\n\n<small>$footer</small></html>";
		$message["body"] = nl2br($message["body"]);

		foreach ($subscribers AS $subkey => $subvalue){
			//echo "\nFound subsciberID: " . $subvalue["user_id"] . "\n";
			$usermails =  get_user_mailarray($subvalue["user_id"]);
			//var_dump($usermails);

			foreach($usermails as $mailkey => $mailvalue){
				array_push($message["to"], $mailvalue["value"]);
			}

			//var_dump($message);
		}
		$json = json_encode($message);

		$mystatus = elasmail_queue($json);  // non-existing function!
		if($mystatus == 1){
			$query = "UPDATE mailq SET  sent = 1 WHERE msgid = '" . $message["id"] ."'";
			$db->Execute($query);
			$mid = $message["id"];
			log_event("","Mail","Queued $mid to ESM");
		} else {
			echo "Failed to AMQ queue message " . $message["id"] ."\n";
			log_event("","Mail","Failed to queue $mid to eLAS Mailer");
		}
	}
	echo "\n";

	write_timestamp("mailq_run");
}

function cat_update_count() {
	echo "Running cat_update_count\n";
        $catlist = get_cat();
        foreach ($catlist AS $key => $value){
                $cat_id = $value["id"];
                update_stat_msgs($cat_id);
        }

	write_timestamp("cat_update_count");
}

function saldo_update(){
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

function publish_news(){
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

function cleanup_messages(){
	// Fetch a list of all expired messages that are beyond the grace period and delete them
	echo "Running cleanup_messages\n";
	do_auto_cleanup_messages();
	do_auto_cleanup_inactive_messages();
	write_timestamp("cleanup_messages");
}

function cleanup_tokens(){
	echo "Running cleanup_tokens\n";
	do_cleanup_tokens();
	write_timestamp("cleanup_tokens");
}

function cleanup_news() {
	echo "Running cleanup_news\n";
	do_cleanup_news();
	write_timestamp("cleanup_news");
}

function check_user_exp_msgs(){
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

function automail_admin_exp_msg(){
	// Fetch a list of all expired messages and mail them to the admin
	echo "Running automail_admin_exp_msg\n";
	global $db;
	$today = date("Y-m-d");
	$query = "SELECT users.name AS username, messages.content AS message, messages.id AS mid, messages.validity AS validity FROM messages,users WHERE users.status <> 0 AND messages.id_user = users.id AND validity <= '" .$today ."'";
	$messages = $db->GetArray($query);

	mail_admin_expmsg($messages);

	write_timestamp("admin_exp_msg");
}

function automail_saldo(){
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
			AND users.status <> 0
			AND users.cron_saldo = 1';
	$users = $db->GetArray($query);

	foreach($users as $key => $value) {
		$mybalance = $value["saldo"];
		mail_balance($value["cvalue"], $mybalance);
	}

	//Timestamp this run
	write_timestamp("saldo");
}
