<?php
ob_start();

//

/**
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_amq.php");
**/
$r = "\r\n";

$php_sapi_name = php_sapi_name();

if ($php_sapi_name == 'cli')
{
	echo 'the cron should not run from the cli but from the http web server.' . $r;
	exit;
}

defined('__DIR__') or define('__DIR__', dirname(__FILE__));
chdir(__DIR__);

$rootpath = "../";

require_once $rootpath . 'vendor/autoload.php';
require_once $rootpath . 'includes/inc_redis.php';
require_once $rootpath . 'includes/inc_setstatus.php';
require_once $rootpath . 'includes/inc_timezone.php';
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
// echo 'version: ' . exec('git describe') . $r; (no git available)
echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r . $r;

$sessions = $domains = $sessions_cron_timestamps = array();

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

		$sessions_cron_timestamps[$session_name] = (int) $redis->get($session_name . '_cron_timestamp');
	}
}

unset($session_name, $domain);

if (count($sessions))
{
	asort($sessions_cron_timestamps);
	echo 'Session name (domain): last cron timestamp' . $r;
	echo '-----------------------------------------' . $r;
	foreach ($sessions_cron_timestamps as $session_n => $timestamp)
	{
		echo $session_name . ' (' . $domains[$session_n] . '): ' . $timestamp;
		if (!isset($db_url))
		{
			$db_url = $_ENV['HEROKU_POSTGRESQL_' . $session_name . '_URL'];
			$session_name = $session_n;
			echo ' (selected)';
		}
		echo $r;
	}
}
else
{
	$db_url = getenv('DATABASE_URL');
	echo '-- No installed domains found. Select default database --' . $r . $r;
}

$db = NewADOConnection($db_url);

unset($db_url);

$db->SetFetchMode(ADODB_FETCH_ASSOC);

if(getenv('ELAS_DB_DEBUG')){
	$db->debug = true;
}

function getadoerror(){
	$e = ADODB_Pear_Error();
        if(is_object($e)){
                        return $e->message;
        }
	return FALSE;
}

# Upgrade the DB first if required

$query = "SELECT * FROM parameters WHERE parameter= 'schemaversion'";
$qresult = $db->GetRow($query) ;
$dbversion = $qresult["value"];
$currentversion = $dbversion;
$doneversion = $currentversion;
echo "eLAS database needs to upgrade from $currentversion to $schemaversion\n\n";
while($currentversion < $schemaversion){
	//echo "Doing upgrade #" .$currentversion ."\n\n";
	$currentversion = $currentversion +1;
	if(doupgrade($currentversion) == TRUE){
		$doneversion = $currentversion;
	}
}
echo "Upgraded database from schema version $dbversion to $doneversion\n\n";
log_event("","DB","Upgraded database from schema version $dbversion to $doneversion");

// PUT MAIN BODY HERE
// echo "<p><small>Build from branch: " . $elas->branch .", revision: " .$elas->revision .", build: " .$elas->build;
echo " *** eLAS v" .$elas->version . "(" .$elas->branch .")" ." build #" . $elas->build ." Cron system running [" .readconfigfromdb("systemtag") ."] ***\n\n";

/*
// Check and create required paths
$frequency = 10;
if(check_timestamp("create_paths", $frequency) == 1) {
	create_paths();
}*/

// Check for incoming messages on the AMQ
//FIXME set frequency to 5
/*

$frequency = 0;
if(check_timestamp("process_ampmessages", $frequency) == 1) {
	process_amqmessages();
}

*/

// Auto mail saldo on request
$frequency = readconfigfromdb("saldofreqdays") * 1440;
if(check_timestamp("saldo", $frequency) == 1) {
	automail_saldo();
}

// Auto mail messages that have expired to the admin
$frequency = readconfigfromdb("adminmsgexpfreqdays") * 1440;
if(check_timestamp("admin_exp_msg", $frequency) == 1 && readconfigfromdb("adminmsgexp") == 1){
	automail_admin_exp_msg();
}

// Check for and mail expired messages to the user
$frequency = 1440;
if(check_timestamp("user_exp_msgs", $frequency) == 1 && readconfigfromdb("msgexpwarnenabled") == 1){
	check_user_exp_msgs();
}

/*
// Clean up expired messages after the grace period
$frequency = 1440;
if(check_timestamp("cleanup_messages", $frequency) == 1 && readconfigfromdb("msgcleanupenabled") == 1){
        cleanup_messages();
}
*
*/

// Update counts for each message category
$frequency = 60;
if(check_timestamp("cat_update_count", $frequency) == 1) {
        cat_update_count();
}

// Update the cached saldo
$frequency = 60;
if(check_timestamp("saldo_update", $frequency) == 1) {
	saldo_update();
}

// Clean up expired news items
$frequency = 1440;
if(check_timestamp("cleanup_news", $frequency) == 1){
        cleanup_news();
}

// Clean up expired tokens
$frequency = 1440;
if(check_timestamp("cleanup_tokens", $frequency) == 1){
        cleanup_tokens();
}

// RUN the ILPQ
$frequency = 5;
if(check_timestamp("processqueue", $frequency) == 1){
	require_once("$rootpath/interlets/processqueue.php");
	write_timestamp("processqueue");
}

// Publish news items that were approved
$frequency = 30;
if(check_timestamp("publish_news", $frequency) == 1){
	publish_news();
}

// Update the stats table
$frequency = 720;
if(check_timestamp("update_stats", $frequency) == 1){
	update_stats();
}

/*
$frequency = 60;
if(check_timestamp("publish_mailinglists", $frequency) == 1 && readconfigfromdb("mailinglists_enabled") == 1){
	publish_mailinglists();
}

*/

// END
echo "\nCron run finished\n";

////////////////////////////////////////////////////////////////////////////
//////////////////////////////F U N C T I E S //////////////////////////////
////////////////////////////////////////////////////////////////////////////

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

    $query = "SELECT * FROM news WHERE approved = 1 AND published IS NULL OR published = 0;";
	$newsitems = $db->GetArray($query);

    foreach ($newsitems AS $key => $value){
		mail_news($value["id"]);

		$q2 = "UPDATE news SET published=1 WHERE id=" .$value["id"];
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

	//foreach($messages as $key => $value) {
	//	echo $value["mid"];
	//	echo $value["username"];
	//	echo $value["message"];
	//	echo $value["validity"];
	//	echo "\n";
	//}
	mail_admin_expmsg($messages);

	write_timestamp("admin_exp_msg");
}

function automail_saldo(){
	// Get all users that want their saldo auto-mailed.
	echo "Running automail_saldo\n";
	global $db;
        $query = "SELECT users.id, users.name, users.saldo AS saldo, contact.value AS cvalue FROM users,contact,type_contact ";
	$query .= "WHERE users.id = contact.id_user AND contact.id_type_contact = type_contact.id AND type_contact.abbrev = 'mail' AND users.status <> 0 AND users.cron_saldo = 1";
	$users = $db->GetArray($query);

	foreach($users as $key => $value) {
		$mybalance = $value["saldo"];
		mail_balance($value["cvalue"], $mybalance);
	}

	//Timestamp this run
	write_timestamp("saldo");
}
