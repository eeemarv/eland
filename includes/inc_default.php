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

$cdn_jqplot = (getenv('ELAS_CDN_JQPLOT')) ?: '//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/';
$cdn_jquery = (getenv('ELAS_CDN_JQUERY')) ?: '//code.jquery.com/jquery-2.1.3.min.js';
$cdn_jqueryui = (getenv('ELAS_CDN_JQUERYUI')) ?: '//code.jquery.com/ui/1.11.4/jquery-ui.min.js';
$cdn_typeahead = (getenv('ELAS_CDN_TYPEAHEAD')) ?: '//cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.10.4/typeahead.bundle.min.js';

require_once $rootpath . 'vendor/autoload.php';
require_once $rootpath . 'includes/inc_eventlog.php';
require_once $rootpath . 'includes/inc_session.php'; 
require_once $rootpath . 'includes/inc_redis.php';
require_once $rootpath . 'includes/inc_setstatus.php';
require_once $rootpath . 'includes/inc_timezone.php';
require_once $rootpath . 'includes/inc_version.php';
require_once $rootpath . 'includes/inc_alert.php';

$alert = new alert();
