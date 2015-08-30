<?php

$baseurl = $_SERVER['HTTP_HOST'];

if(!isset($rootpath))
{
	$rootpath = '';
}

$cdn_bootstrap_css = (getenv('ELAS_CDN_BOOTSTRAP_CSS')) ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css';
$cdn_bootstrap_js = (getenv('ELAS_CDN_BOOTSTRAP_JS')) ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js';
$cdn_fontawesome = (getenv('ELAS_CDN_FONTAWESOME')) ?: '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css';

$cdn_footable_js = (getenv('ELAS_CDN_FOOTABLE_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/js/footable.js';
$cdn_footable_sort_js = (getenv('ELAS_CDN_FOOTABLE_SORT_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/js/footable.sort.js';
$cdn_footable_filter_js = (getenv('ELAS_CDN_FOOTABLE_FILTER_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/js/footable.filter.js';
$cdn_footable_css = (getenv('ELAS_CDN_FOOTABLE_CSS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/css/footable.core.min.css';
$cdn_jssor_slider_mini_js = (getenv('ELAS_CDN_JSSOR_MINI_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jssor/js/jssor.slider.mini.js';

$cdn_jqplot = (getenv('ELAS_CDN_JQPLOT')) ?: '//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/';
$cdn_jquery = (getenv('ELAS_CDN_JQUERY')) ?: '//code.jquery.com/jquery-2.1.3.min.js';

$cdn_jquery_ui_widget = (getenv('ELAS_CDN_JQUERY_UI_WIDGET')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/vendor/jquery.ui.widget.js';
$cdn_jquery_iframe_transport = (getenv('ELAS_CDN_JQUERY_IFRAME_TRANSPORT')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.iframe-transport.js';
$cdn_load_image = (getenv('ELAS_CDN_LOAD_IMAGE')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/JavaScript-Load-Image-1.14.0/js/load-image.all.min.js';
$cdn_canvas_to_blob = (getenv('ELAS_CDN_CANVAS_TO_BLOB')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/JavaScript-Canvas-to-Blob-2.2.0/js/canvas-to-blob.min.js';
$cdn_jquery_fileupload = (getenv('ELAS_CDN_JQUERY_FILEUPLOAD')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.fileupload.js';
$cdn_jquery_fileupload_process = (getenv('ELAS_CDN_JQUERY_FILEUPLOAD_PROCESS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.fileupload-process.js';
$cdn_jquery_fileupload_image = (getenv('ELAS_CDN_JQUERY_FILEUPLOAD_IMAGE')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.fileupload-image.js';
$cdn_jquery_fileupload_validate = (getenv('ELAS_CDN_JQUERY_FILEUPLOAD_VALIDATE')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.fileupload-validate.js';

$cdn_typeahead = (getenv('ELAS_CDN_TYPEAHEAD')) ?: '//cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.10.4/typeahead.bundle.min.js';
$cdn_datepicker_css = (getenv('ELAS_CDN_DATEPICKER_CSS')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/css/bootstrap-datepicker.standalone.min.css';
$cdn_datepicker = (getenv('ELAS_CDN_DATEPICKER')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/js/bootstrap-datepicker.min.js';
$cdn_datepicker_nl = (getenv('ELAS_CDN_DATEPICKER_NL')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/locales/bootstrap-datepicker.nl.min.js';

require_once $rootpath . 'vendor/autoload.php';

// Connect to Redis
$redis_url = getenv('REDISTOGO_URL');

if(!empty($redis_url))
{
	Predis\Autoloader::register();
	try
	{
		$redis_con = parse_url($redis_url);
		$redis_con['password'] = $redis_con['pass'];
		$redis_con['scheme'] = 'tcp';
		$redis = new Predis\Client($redis_con);

	}
	catch (Exception $e)
	{
	    echo "Couldn't connected to Redis: ";
	    echo $e->getMessage();
	}
}

/*
 * get session name from environment variable ELAS_SCHEMA_<domain>
 * dots in <domain> are replaced by double underscore __
 * hyphens in <domain> are replaced by triple underscore ___
 *
 * example:
 *
 * to link e-example.com to a session set environment variable
 * ELAS_SCHEMA_E___EXAMPLE__COM = <session_name>
 *
 * + the session name is the schema name !
 * + session name is prefix of the image files.
 * + session name is prefix of keys in Redis.
 *
 */

if (!isset($schema))
{
	$schema = str_replace('.', '__', $_SERVER['HTTP_HOST']);
	$schema = str_replace('-', '___', $schema);
	$schema = str_replace(':', '____', $schema);
	$schema = strtoupper($schema);
	$schema = getenv('ELAS_SCHEMA_' . $schema);
}

if (!$schema)
{
	http_response_code(404);
	include $rootpath. '404.html';
	exit;
}

session_name($schema);
session_start();

$s_id = $_SESSION['id'];
$s_name = $_SESSION['name'];
$s_letscode = $_SESSION['letscode'];
$s_accountrole = $_SESSION['accountrole'];

if (!isset($role) || !$role || (!in_array($role, array('admin', 'user', 'guest', 'anonymous'))))
{
	http_response_code(500);
	include $rootpath . '500.html';
	exit;
}

if ($role != 'anonymous' && (!isset($s_id) || !$s_accountrole || !$s_name))
{
	header('Location: ../login.php?location=' . urlencode($_SERVER['REQUEST_URI']));
	exit;
}

if ((!isset($allow_anonymous_post) && $s_accountrole == 'anonymous' && $_SERVER['REQUEST_METHOD'] != 'GET')
	|| ($s_accountrole == 'guest' && $_SERVER['REQUEST_METHOD'] != 'GET')
	|| ($role == 'admin' && $s_accountrole != 'admin')
	|| ($role == 'user' && !in_array($s_accountrole, array('admin', 'user')))
	|| ($role == 'guest' && !in_array($s_accountrole, array('admin', 'user', 'guest'))))
{
	http_response_code(403);
	include $rootpath . '403.html';
	exit;
}

require_once $rootpath . 'includes/inc_eventlog.php';
require_once $rootpath . 'includes/inc_alert.php';

$alert = new alert();

// default timezone to Europe/Brussels (read from config file removed, use env var instead)
$elas_timezone = getenv('ELAS_TIMEZONE');
$elas_timezone = ($elas_timezone) ? $elas_timezone : 'Europe/Brussels';
date_default_timezone_set($elas_timezone);

$elasdebug = (getenv('ELAS_DEBUG'))? 1 : 0;

// release file (xml) not loaded anymore.
$elasversion = '3.1.17';  // was eLAS 3.1.17 in release file.
$schemaversion= 31000;  // no new versions anymore, release file is not read anymore.
$soapversion = 1200;
$restversion = 1;

// database connection
$db = NewADOConnection(getenv('DATABASE_URL'));

$db->Execute('set search_path to ' . (($schema) ?: 'public'));

$db->SetFetchMode(ADODB_FETCH_ASSOC);

if(getenv('ELAS_DB_DEBUG'))
{
	$db->debug = true;
}

require_once $rootpath . 'includes/inc_dbconfig.php';
