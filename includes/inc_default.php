<?php

// Get rid of missing rootpath errors
if(!isset($rootpath)){
	$rootpath = "";
}



//override the include path, so we pick up the contrib directory first  //
ini_set('include_path',$rootpath.'contrib/includes:'.ini_get('include_path'));

//require_once($rootpath."includes/inc_config.php");

$baseurl = $_SERVER['HTTP_HOST'];

// Get rid of missing rootpath errors
if(!isset($rootpath)){
	$rootpath = "";
}

require_once $rootpath . 'vendor/autoload.php';
require_once $rootpath . 'includes/inc_eventlog.php';
require_once $rootpath . 'includes/inc_session.php'; 
require_once $rootpath . 'includes/inc_redis.php';
require_once $rootpath . 'includes/inc_setstatus.php';
require_once $rootpath . 'includes/inc_timezone.php';

// Debug eLAS, enable extra logging
$elasdebug = (getenv('ELAS_DEBUG'))? 1 : 0;

// release file (xml) not loaded anymore.
$elasversion = '3.1.17';  // was eLAS 3.1.17 in release file.
$schemaversion= 31000;  // no new versions anymore, release file is not read anymore.
$soapversion = 1200;
$restversion = 1;

// provider file not loaded anymore.
