<?php
//require 'vendor/autoload.php';

# Get rid of missing rootpath errors
if(!isset($rootpath)){
	$rootpath = "";
}

require_once($rootpath."vendor/autoload.php");

//override the include path, so we pick up the contrib directory first
ini_set('include_path',$rootpath.'contrib/includes:'.ini_get('include_path'));  

//require_once($rootpath."includes/inc_config.php");

$baseurl = $_SERVER['HTTP_HOST'];

// Get rid of missing rootpath errors
if(!isset($rootpath)){
	$rootpath = "";
}

require_once($rootpath."includes/inc_eventlog.php");

//create a session name on domain stripped from chars (attention! domains must differ in alphanumeric chars)

$session_name = preg_replace('/[^A-Za-z0-9]/', '', $baseurl);

session_name($session_name);
session_start();

// Set the version here
//$elas = simplexml_load_file("release.xml?var=" .rand());
$releasefile = $rootpath ."release.xml";
$elas = simplexml_load_file($releasefile);
if(!empty($elas->suffix)){
	$elasversion =  $elas->version ."-" .$elas->suffix ." #" .$elas->build;
} else {
	$elasversion =  $elas->version ." #" .$elas->build;
}
// What schema version to expect
$schemaversion=$elas->schemaversion;
$soapversion = 1200;
$restversion = 1;

// Load provider data  if hosting is enabled
$providerfile = $rootpath ."sites/provider.xml";
$provider = simplexml_load_file($providerfile);

// Connect to Redis

$redis_url = getenv('REDISTOGO_URL');

if(!empty($redis_url)){
	Predis\Autoloader::register();
	try {
	    $redis = new Predis\Client($redis_url);
	}
	catch (Exception $e) {
	    echo "Couldn't connected to Redis";
	    echo $e->getMessage();
	}
}


// Debug eLAS, enable extra logging
$elasdebug = (getenv('ELAS_DEBUG'))? 1 : 0;

// Hardcode timezone to Europe/Brussels (read from config removed)
date_default_timezone_set('Europe/Brussels');

// Provide transient notifications
function setstatus($status,$flag=0){
	global $provider;
	global $xmlconfig;		
	global $_SESSION;
	
	$s_id = $_SESSION["id"];
}


// Make timestamps for SQL statements
function make_timestamp($timestring){
        $month = substr($timestring,3,2);
        $day = substr($timestring, 0,2);
        $year = substr($timestring,6,4 );
        $timestamp = mktime(0,0,0,$month, $day, $year);
        return $timestamp;
}

?>
