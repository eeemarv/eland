<?php

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

/*
 * get session name from environment variable ELAS_DOMAIN_SESSION_<domain>
 * dots in <domain> are replaced by double underscore __
 * hyphens in <domain> are replaced by triple underscore ___
 *
 * example:
 *
 * to link e-example.com to a session set environment variable
 * ELAS_DOMAIN_SESSION_E___EXAMPLE__COM = <session_name>
 *
 * + the session name has to be set to the color name of the database!
 * + session name is prefix of the image files.
 * + session name is prefix of keys in Redis.
 *
 */

$session_name = str_replace(':', '', $baseurl);
$session_name = str_replace('.', '__', $session_name);
$session_name = str_replace('-', '___', $session_name);
$session_name = strtoupper($session_name);
$session_name = getenv('ELAS_DOMAIN_SESSION_' . $session_name);
$session_name = ($session_name) ? $session_name : 'ELASDEFAULT';

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

// provider file not loaded anymore.

// Connect to Redis
require_once $rootpath . 'includes/inc_redis.php';

// Debug eLAS, enable extra logging
$elasdebug = (getenv('ELAS_DEBUG'))? 1 : 0;

// default timezone to Europe/Brussels (read from config removed)
$elas_timezone = getenv('ELAS_TIMEZONE');
$elas_timezone = ($elas_timezone) ? $elas_timezone : 'Europe/Brussels';
date_default_timezone_set($elas_timezone);

// Provide transient notifications
function setstatus($status,$flag=0){

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
