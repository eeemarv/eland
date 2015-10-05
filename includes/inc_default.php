<?php

$baseurl = $_SERVER['HTTP_HOST'];

if(!isset($rootpath))
{
	$rootpath = '';
}

$cdn_bootstrap_css = (getenv('CDN_BOOTSTRAP_CSS')) ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css';
$cdn_bootstrap_js = (getenv('CDN_BOOTSTRAP_JS')) ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js';
$cdn_fontawesome = (getenv('CDN_FONTAWESOME')) ?: '//maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css';

$cdn_footable_js = (getenv('CDN_FOOTABLE_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/js/footable.js';
$cdn_footable_sort_js = (getenv('CDN_FOOTABLE_SORT_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/js/footable.sort.js';
$cdn_footable_filter_js = (getenv('CDN_FOOTABLE_FILTER_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/js/footable.filter.js';
$cdn_footable_css = (getenv('CDN_FOOTABLE_CSS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/footable-2.0.3/css/footable.core.min.css';
$cdn_jssor_slider_mini_js = (getenv('CDN_JSSOR_MINI_JS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jssor/js/jssor.slider.mini.js';

$cdn_jqplot = (getenv('CDN_JQPLOT')) ?: '//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/';
$cdn_jquery = (getenv('CDN_JQUERY')) ?: '//code.jquery.com/jquery-2.1.3.min.js';

$cdn_jquery_ui_widget = (getenv('CDN_JQUERY_UI_WIDGET')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/vendor/jquery.ui.widget.js';
$cdn_jquery_iframe_transport = (getenv('CDN_JQUERY_IFRAME_TRANSPORT')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.iframe-transport.js';
$cdn_load_image = (getenv('CDN_LOAD_IMAGE')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/JavaScript-Load-Image-1.14.0/js/load-image.all.min.js';
$cdn_canvas_to_blob = (getenv('CDN_CANVAS_TO_BLOB')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/JavaScript-Canvas-to-Blob-2.2.0/js/canvas-to-blob.min.js';
$cdn_jquery_fileupload = (getenv('CDN_JQUERY_FILEUPLOAD')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.fileupload.js';
$cdn_jquery_fileupload_process = (getenv('CDN_JQUERY_FILEUPLOAD_PROCESS')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.fileupload-process.js';
$cdn_jquery_fileupload_image = (getenv('CDN_JQUERY_FILEUPLOAD_IMAGE')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.fileupload-image.js';
$cdn_jquery_fileupload_validate = (getenv('CDN_JQUERY_FILEUPLOAD_VALIDATE')) ?: 'http://elas-c.s3-website.eu-central-1.amazonaws.com/jQuery-File-Upload-9.10.4/js/jquery.fileupload-validate.js';

$cdn_typeahead = (getenv('CDN_TYPEAHEAD')) ?: '//cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.11.1/typeahead.bundle.min.js';
$cdn_datepicker_css = (getenv('CDN_DATEPICKER_CSS')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/css/bootstrap-datepicker.standalone.min.css';
$cdn_datepicker = (getenv('CDN_DATEPICKER')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/js/bootstrap-datepicker.min.js';
$cdn_datepicker_nl = (getenv('CDN_DATEPICKER_NL')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/locales/bootstrap-datepicker.nl.min.js';

$cdn_ckeditor = (getenv('CDN_CKEDITOR')) ?: '//cdn.ckeditor.com/4.5.3/standard/ckeditor.js';

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
$s_rights = $_SESSION['rights'];

$access_ary = array(
	'admin'		=> 0,
	'user'		=> 1,
	'guest'		=> 2,
	'anonymous'	=> 3,
);

$acc_ary = array(
	0	=> array('admin', 'default'),
	1	=> array('leden', 'warning'),
	2	=> array('interlets', 'success'),
);

$access_options = array(
	'0'	=> 'admin',
	'1'	=> 'leden',
	'2' => 'interlets',
);

$access_level = (isset($access_ary[$s_accountrole])) ? $access_ary[$s_accountrole] : 3;

$s_admin = ($s_accountrole == 'admin') ? true : false;
$s_user = ($s_accountrole == 'user') ? true : false;
$s_guest = ($s_accountrole == 'guest') ? true : false;
$s_anonymous = ($s_accountrole == 'anonymous') ? true : false;

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

if ((!isset($allow_anonymous_post) && $s_anonymous && $_SERVER['REQUEST_METHOD'] != 'GET')
	|| ($s_guest && $_SERVER['REQUEST_METHOD'] != 'GET')
	|| ($role == 'admin' && !$s_admin)
	|| ($role == 'user' && !($s_admin || $s_user))
	|| ($role == 'guest' && !($s_admin || $s_user || $s_guest)))
{
	http_response_code(403);
	include $rootpath . '403.html';
	exit;
}

require_once $rootpath . 'includes/elas_mongo.php';

$elas_mongo = new elas_mongo($schema);

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

$config = new \Doctrine\DBAL\Configuration();

$db = \Doctrine\DBAL\DriverManager::getConnection(array(
	'url' => getenv('DATABASE_URL'),
), $config);

$db->exec('set search_path to ' . ($schema) ?: 'public');

/*
 * vars
 */

$elas_heroku_config = array(
	'users_can_edit_username'	=> array('0', 'Gebruikers kunnen zelf hun gebruikersnaam aanpassen [0, 1]'),
	'users_can_edit_fullname'	=> array('0', 'Gebruikers kunnen zelf hun volledige naam (voornaam + achternaam) aanpassen [0, 1]'),
	'registration_en'			=> array('0', 'Inschrijvingsformulier ingeschakeld [0, 1]'),
);

$top_right = '';
$top_buttons = '';

/*
 * functions
 */
 
function readconfigfromdb($key)
{
    global $db, $schema, $redis;
    global $elas_mongo, $elas_heroku_config;
    static $cache;

	if (isset($cache[$key]))
	{
		return $cache[$key];
	}

	$redis_key = $schema . '_config_' . $key;

	if ($redis->exists($redis_key))
	{
		return $cache[$key] = $redis->get($redis_key);
	}

	if (isset($elas_heroku_config[$key]))
	{
		$elas_mongo->connect();
		$s = $elas_mongo->settings->findOne(array('name' => $key));
		$value = (isset($s['value'])) ? $s['value'] : $elas_heroku_config[$key][0];
	}
	else
	{
		$value = $db->fetchColumn('SELECT value FROM config WHERE setting = ?', array($key));
	}

	if (isset($value))
	{
		$redis->set($redis_key, $value);
		$redis->expire($redis_key, 2592000);
		$cache[$key] = $value;
	}

	return $value;
}

/**
 *
 */
function writeconfig($key, $value)
{
	global $db, $redis, $schema;
	global $elas_heroku_config, $elas_mongo;

	if ($elas_heroku_config[$key])
	{
		$a = array(
			'value' => $value,
			'name'	=> $key
		);
		$elas_mongo->settings->update(array('name' => $key), $a, array('upsert' => true));
	}
	else
	{
		if (!$db->update('config', array('value' => $value, '"default"' => 'f'), array('setting' => $key)))
		{
			return false;
		}
	}

	$redis_key = $schema . '_config_' . $key;
	$redis->set($redis_key, $value);
	$redis->expire($redis_key, 2592000);

	return true;
}

/**
 *
 */
function readparameter($key, $refresh = false)
{
    global $db, $schema, $redis;
    static $cache;

	if (!$refresh)
	{
		if (isset($cache[$key]))
		{
			return $cache[$key];
		}

		$redis_key = $schema . '_parameters_' . $key;

		if ($redis->exists($redis_key))
		{
			return $cache[$key] = $redis->get($redis_key);
		}
	}

	$value = $db->fetchColumn('SELECT value FROM parameters WHERE parameter = ?', array($key));

	if (isset($value))
	{
		$redis->set($redis_key, $value);
		$redis->expire($redis_key, 2592000);
		$cache[$key] = $value;
	}

	return $value;
}

/**
 *
 */
function readuser($id, $refresh = false)
{
    global $db, $schema, $redis, $elas_mongo;
    static $cache;

	if (!$id)
	{
		return array();
	}

	$redis_key = $schema . '_user_' . $id;	

	if (!$refresh)
	{
		if (isset($cache[$id]))
		{
			return $cache[$id];
		}

		if ($redis->exists($redis_key))
		{
			return $cache[$id] = unserialize($redis->get($redis_key));
		} 
	}

	$user = $db->fetchAssoc('SELECT * FROM users WHERE id = ?', array($id));

	$elas_mongo->connect();
	$user += (is_array($ary = $elas_mongo->users->findOne(array('id' => $id)))) ? $ary : array();

	if (isset($user))
	{
		$redis->set($redis_key, serialize($user));
		$redis->expire($redis_key, 2592000);
		$cache[$id] = $user;
	}

	return $user;
}

/*
 *
 */
function sendemail($from, $to, $subject, $content)
{
	global $s_id;

	if (!readconfigfromdb('mailenabled'))
	{
		log_event('', 'mail', 'Mail ' . $subject . ' not sent, mail functions are disabled');
		return 'Mail functies zijn uitgeschakeld';
	}

	if(empty($from) || empty($to) || empty($subject) || empty($content))
	{
		$log = "Mail $subject not sent, missing fields\n";
		$log .= "From: $from\nTo: $to\nSubject: $subject\nContent: $content";
		log_event("", "mail", $log);
		return 'Fout: mail niet verstuurd, ontbrekende velden';
	}

	$to = (is_array($to)) ? implode(',', $to) : $to;

	$to = trim($to, ',');

	$to = explode(',', $to);

	$to_mandrill = array_map(function($email_address){return array('email' => $email_address);}, $to);

	$message = array(
		'subject'		=> $subject,
		'text'			=> $content,
		'from_email'	=> $from,
		'to'			=> $to_mandrill,
	);

	try {
		$mandrill = new Mandrill(); 
		$mandrill->messages->send($message, true);
	}
	catch (Mandrill_Error $e)
	{
		log_event($s_id, 'mail', 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage());
		return 'Mail niet verzonden. Fout in mail service.';
	}

	$to = (is_array($to)) ? implode(', ', $to) : $to;

	log_event($s_id, 'mail', 'mail sent, subject: ' . $subject . ', from: ' . $from . ', to: ' . $to);

	return false;
}

/*
 *
 */
function render_select_options($option_ary, $selected)
{
	foreach ($option_ary as $key => $value)
	{
		echo '<option value="' . $key . '"';
		echo ($key == $selected) ? ' selected="selected"' : '';
		echo '>' . htmlspecialchars($value, ENT_QUOTES) . '</option>';
	}
}

/**
 *
 */

function link_user($user, $render = null, $link = true)
{
	global $rootpath;
	$user = (is_array($user)) ? $user : readuser($user);
	$str = (isset($render)) ? $user[$render] : $user['letscode'] . ' ' . $user['name'];
	$str = htmlspecialchars($str, ENT_QUOTES);
	return ($link) ? '<a href="' . $rootpath . 'users.php?id=' . $user['id'] . '">' . $str . '</a>' : $str;
}
