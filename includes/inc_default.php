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
if(!isset($rootpath))
{
	$rootpath = "";
}

$cdn_bootstrap_css = (getenv('ELAS_CDN_BOOTSTRAP_CSS')) ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css';
$cdn_bootstrap_js = (getenv('ELAS_CDN_BOOTSTRAP_JS')) ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js';
$cdn_fontawesome = (getenv('ELAS_CDN_FONTAWESOME')) ?: '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css';

$cdn_footable_js = (getenv('ELAS_CDN_FOOTABLE_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/js/footable.js';
$cdn_footable_sort_js = (getenv('ELAS_CDN_FOOTABLE_SORT_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/js/footable.sort.js';
$cdn_footable_css = (getenv('ELAS_CDN_FOOTABLE_CSS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/css/footable.core.min.css';

$cdn_jqplot = (getenv('ELAS_CDN_JQPLOT')) ?: '//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/';
$cdn_jquery = (getenv('ELAS_CDN_JQUERY')) ?: '//code.jquery.com/jquery-2.1.3.min.js';
$cdn_typeahead = (getenv('ELAS_CDN_TYPEAHEAD')) ?: '//cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.10.4/typeahead.bundle.min.js';
$cdn_datepicker = (getenv('ELAS_CDN_DATEPICKER')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/js/bootstrap-datepicker.min.js';
$cdn_datepicker_nl = (getenv('ELAS_CDN_DATEPICKER_NL')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/locales/bootstrap-datepicker.nl.min.js';

require_once $rootpath . 'vendor/autoload.php';
require_once $rootpath . 'includes/inc_session.php';
require_once $rootpath . 'includes/inc_eventlog.php';
require_once $rootpath . 'includes/inc_redis.php';
require_once $rootpath . 'includes/inc_setstatus.php';
require_once $rootpath . 'includes/inc_timezone.php';
require_once $rootpath . 'includes/inc_version.php';
require_once $rootpath . 'includes/inc_alert.php';

$alert = new alert();
