<?php
//require 'vendor/autoload.php';

# Get rid of missing rootpath errors
if(!isset($rootpath)){
	$rootpath = "";
}

require_once($rootpath."vendor/autoload.php");

//override the include path, so we pick up the contrib directory first
ini_set('include_path',$rootpath.'contrib/includes:'.ini_get('include_path'));  
#echo ini_get('include_path');
require_once($rootpath."includes/inc_config.php");
require_once($rootpath."includes/inc_eventlog.php");

session_name($configuration["system"]["sessionname"]);
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
if(!empty($provider->redishost)){
	$redishost = $provider->redishost;
	Predis\Autoloader::register();
	try {
	    $redis = new Predis\Client();
	    $redis = new Predis\Client(array(
        	"scheme" => "tcp",
	        "host" => "$redishost",
	        "port" => 6379));
	   //echo "Connected to Redis host";
	}
	catch (Exception $e) {
	    echo "Couldn't connected to Redis";
	    echo $e->getMessage();
	}
}


// Debug eLAS, enable extra logging
if(!empty($xmlconfig->debug)) {
	$elasdebug = $xmlconfig->debug;
} else {
	$elasdebug = 0;
}

// Set the timezeone to value in configuration
date_default_timezone_set($configuration["system"]["timezone"]);

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
