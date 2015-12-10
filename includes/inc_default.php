<?php

if(!isset($rootpath))
{
	$rootpath = '';
}

ob_start('etag_buffer');

$s3_res = getenv('S3_RES') ?: die('Environment variable S3_RES S3 bucket for resources not defined.');
$s3_img = getenv('S3_IMG') ?: die('Environment variable S3_IMG S3 bucket for images not defined.');
$s3_doc = getenv('S3_DOC') ?: die('Environment variable S3_DOC S3 bucket for documents not defined.');

$s3_res_url = 'http://' . $s3_res . '/';
$s3_img_url = 'http://' . $s3_img . '/';
$s3_doc_url = 'http://' . $s3_doc . '/';

$script_name = ltrim($_SERVER['SCRIPT_NAME'], '/');
$script_name = str_replace('.php', '', $script_name);

$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
$port = ($_SERVER['SERVER_PORT'] == '80') ? '' : ':' . $_SERVER['SERVER_PORT'];
$base_url = $http . $_SERVER['SERVER_NAME'] . $port;

$post = ($_SERVER['REQUEST_METHOD'] == 'GET') ? false : true;

$cdn_bootstrap_css = (getenv('CDN_BOOTSTRAP_CSS')) ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css';
$cdn_bootstrap_js = (getenv('CDN_BOOTSTRAP_JS')) ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js';
$cdn_fontawesome = (getenv('CDN_FONTAWESOME')) ?: '//maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css';

$cdn_footable_js = $s3_res_url . 'footable-2.0.3/js/footable.js';
$cdn_footable_sort_js = $s3_res_url . 'footable-2.0.3/js/footable.sort.js';
$cdn_footable_filter_js = $s3_res_url . 'footable-2.0.3/js/footable.filter.js';
$cdn_footable_css = $s3_res_url . 'footable-2.0.3/css/footable.core.css';

/*
$cdn_footable_js = $s3_res_url . 'footable-bootstrap-3.0.3/js/footable.js';
$cdn_footable_css = $s3_res_url . 'footable-bootstrap-3.0.3/css/footable.bootstrap.css';
*/

$cdn_jssor_slider_mini_js = $s3_res_url . 'jssor/js/jssor.slider.mini.js';

$cdn_jqplot = (getenv('CDN_JQPLOT')) ?: '//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/';
$cdn_jquery = (getenv('CDN_JQUERY')) ?: '//code.jquery.com/jquery-2.1.3.min.js';

$cdn_jquery_ui_widget = $s3_res_url . 'jQuery-File-Upload-9.10.4/js/vendor/jquery.ui.widget.js';
$cdn_jquery_iframe_transport = $s3_res_url . 'jQuery-File-Upload-9.10.4/js/jquery.iframe-transport.js';
$cdn_load_image = $s3_res_url . 'JavaScript-Load-Image-1.14.0/js/load-image.all.min.js';
$cdn_canvas_to_blob = $s3_res_url . 'JavaScript-Canvas-to-Blob-2.2.0/js/canvas-to-blob.min.js';
$cdn_jquery_fileupload = $s3_res_url . 'jQuery-File-Upload-9.10.4/js/jquery.fileupload.js';
$cdn_jquery_fileupload_process = $s3_res_url . 'jQuery-File-Upload-9.10.4/js/jquery.fileupload-process.js';
$cdn_jquery_fileupload_image = $s3_res_url . 'jQuery-File-Upload-9.10.4/js/jquery.fileupload-image.js';
$cdn_jquery_fileupload_validate = $s3_res_url . 'jQuery-File-Upload-9.10.4/js/jquery.fileupload-validate.js';
$cdn_fileupload_css = $s3_res_url . 'jQuery-File-Upload-9.10.4/css/jquery.fileupload.css';

$cdn_typeahead = (getenv('CDN_TYPEAHEAD')) ?: '//cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.11.1/typeahead.bundle.min.js';
$cdn_datepicker_css = (getenv('CDN_DATEPICKER_CSS')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/css/bootstrap-datepicker.standalone.min.css';
$cdn_datepicker = (getenv('CDN_DATEPICKER')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/js/bootstrap-datepicker.min.js';
$cdn_datepicker_nl = (getenv('CDN_DATEPICKER_NL')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/locales/bootstrap-datepicker.nl.min.js';

$cdn_ckeditor = (getenv('CDN_CKEDITOR')) ?: '//cdn.ckeditor.com/4.5.3/standard/ckeditor.js';

$cdn_isotope = (getenv('CDN_ISOTOPE')) ?: '//cdnjs.cloudflare.com/ajax/libs/jquery.isotope/2.2.2/isotope.pkgd.min.js';

require_once $rootpath . 'vendor/autoload.php';

// Connect to Redis
$redis_url = getenv('REDISCLOUD_URL');

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
	    echo 'Couldn\'t connected to Redis: ';
	    echo $e->getMessage();
	}
}

/* vars */

$elas_heroku_config = array(
	'users_can_edit_username'	=> array('0', 'Gebruikers kunnen zelf hun gebruikersnaam aanpassen [0, 1]'),
	'users_can_edit_fullname'	=> array('0', 'Gebruikers kunnen zelf hun volledige naam (voornaam + achternaam) aanpassen [0, 1]'),
	'registration_en'			=> array('0', 'Inschrijvingsformulier ingeschakeld [0, 1]'),
//	'forum_en'					=> array('0', 'Forum ingeschakeld [0, 1]'),
	'css'						=> array('', 'Extra stijl: url van .css bestand'),
);

$top_right = '';
$top_buttons = '';

$role_ary = array(
	'admin'		=> 'Admin',
	'user'		=> 'User',
	//'guest'		=> 'Guest', //is not a primary role, but a speudo role
	'interlets'	=> 'Interlets',
);

$status_ary = array(
	0	=> 'Gedesactiveerd',
	1	=> 'Actief',
	2	=> 'Uitstapper',
	//3	=> 'Instapper',    // not used in selector
	//4 => 'Secretariaat, // not used
	5	=> 'Info-pakket',
	6	=> 'Info-moment',
	7	=> 'Extern',
);

$access_ary = array(
	'admin'		=> 0,
	'user'		=> 1,
	'guest'		=> 2,
	'anonymous'	=> 3,
);

$acc_ary = array(
	0	=> array('admin', 'info'),
	1	=> array('leden', 'warning'),
	2	=> array('interlets', 'success'),
);

$access_options = array(
	'0'	=> 'admin',
	'1'	=> 'leden',
	'2' => 'interlets',
);

/*
 * get session name from environment variable SCHEMA_<domain>
 * dots in <domain> are replaced by double underscore __
 * hyphens in <domain> are replaced by triple underscore ___
 *
 * example:
 *
 * to link e-example.com to a session set environment variable
 * SCHEMA_E___EXAMPLE__COM = <session_name>
 *
 * + the session name is the schema name !
 * + session name is prefix of the image files.
 * + session name is prefix of keys in Redis.
 *
 */

$schema = str_replace('.', '__', $_SERVER['HTTP_HOST']);
$schema = str_replace('-', '___', $schema);
$schema = str_replace(':', '____', $schema);
$schema = strtoupper($schema);
$schema = getenv('SCHEMA_' . $schema);

if (!$schema)
{
	http_response_code(404);
	include $rootpath. 'tpl/404.html';
	exit;
}

require_once $rootpath . 'includes/redis_session.php';

$redis_session = new redis_session($redis, $schema);
session_set_save_handler($redis_session, $schema);
session_name($schema);
session_start();

require_once $rootpath . 'includes/inc_alert.php';

$alert = new alert();

$p_role = (isset($_GET['r'])) ? $_GET['r'] : 'anonymous';
$p_user = (isset($_GET['u'])) ? $_GET['u'] : false;
$p_schema = (isset($_GET['s'])) ? $_GET['s'] : false;

$access_request = $access_ary[$p_role];
$access_page = $access_ary[$role];

if (!isset($access_page))
{
	http_response_code(500);
	include $rootpath . 'tpl/500.html';
	exit;
}

$access_session = (isset($_SESSION['accountrole'])) ? $access_ary[$_SESSION['accountrole']] : 3;

if (($access_session == 3) && ($access_page < 3) && ($script_name != 'login'))
{
	set_request_to_session();
	redirect_login();
}
else if (($access_session > $access_page)
	|| (($access_page == 3) && ($access_session < 3) && !isset($allow_session)))
{
	set_request_to_session();
	redirect_index();
}

if ((($access_request != $access_session) && ($access_session > 1))
	|| (($access_session < 2) && ($access_request == 3))
	|| (($access_session == 1) && ($access_request == 0)))
{
	set_request_to_session();
	redirect();
}

if (($access_request > $access_page) && ($access_page < 3))
{
	set_request_to_session();

	if ($access_request > $access_page)
	{
		if ($access_page == 2)
		{
			redirect_login();
		}
		else
		{
			redirect_index();
		}
	}

	redirect();
}

if (($access_page == 3) && ($access_request < 3) && !isset($allow_session))
{
	redirect_index();
}

$access_level = $access_request;

$s_id = $_SESSION['id'];
$s_name = $_SESSION['name'];
$s_letscode = $_SESSION['letscode'];
$s_accountrole = $p_role;
$s_interlets = $_SESSION['interlets'];

$s_admin = ($s_accountrole == 'admin') ? true : false;
$s_user = ($s_accountrole == 'user') ? true : false;
$s_guest = ($s_accountrole == 'guest') ? true : false;
$s_anonymous = ($s_admin || $s_user || $s_guest) ? false : true;

/**
 *
 */
require_once $rootpath . 'includes/elas_mongo.php';

$elas_mongo = new elas_mongo($schema);

require_once $rootpath . 'includes/inc_eventlog.php';

// default timezone to Europe/Brussels (read from config file removed, use env var instead)

date_default_timezone_set((getenv('TIMEZONE')) ?: 'Europe/Brussels');

$elasdebug = (getenv('DEBUG'))? 1 : 0;

// release file (xml) not loaded anymore.
// $elasversion = '3.1.17';  // was eLAS 3.1.17 in release file.
$schemaversion = 31000;  // no new versions anymore, release file is not read anymore.
// $soapversion = 1200;
// $restversion = 1;

// database connection

$db = \Doctrine\DBAL\DriverManager::getConnection(array(
	'url' => getenv('DATABASE_URL'),
), new \Doctrine\DBAL\Configuration());

$db->exec('set search_path to ' . ($schema) ?: 'public');

/**/

$systemname = readconfigfromdb('systemname');
$systemtag = readconfigfromdb('systemtag');
$currency = readconfigfromdb('currency');
$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

/* view */

$view = (isset($_GET['view'])) ? $_GET['view'] : false;

$key_view_users = $schema . '_u_' . $s_id . '_u_view';
$key_view_messages = $schema . '_u_' . $s_id . '_m_view';

$view_users = ($redis->get($key_view_users)) ?: 'list';
$view_messages = ($redis->get($key_view_messages)) ?: 'list';

if ($view)
{
	if ($script_name == 'users' && $view != $view_users)
	{
		$redis->set($key_view_users, $view);
		$view_users = $view;
	}
	else if ($script_name == 'messages' && $view != $view_messages)
	{
		$redis->set($key_view_messages, $view);
		$view_messages = $view;
	}
}

/*
 * create links with query parameters depending on user and role
 */

function aphp($entity = '', $params = '', $label = '*link*', $class = false, $title = false, $fa = false, $collapse = false, $attr = false)
{
	$out = '<a href="' .  generate_url($entity, $params) . '"';
	$out .= ($class) ? ' class="' . $class . '"' : '';
	$out .= ($title) ? ' title="' . $title . '"' : '';
	if (is_array($attr))
	{
		foreach ($attr as $name => $val)
		{
			$out .= ' ' . $name . '="' . $val . '"';
		}
	}
	$out .= '>';
	$out .= ($fa) ? '<i class="fa fa-' . $fa .'"></i>' : '';
	$out .= ($collapse) ? '<span class="hidden-xs hidden-sm"> ' : ' ';
	$out .= (is_array($label)) ? $label[0] : htmlspecialchars($label, ENT_QUOTES);
	$out .= ($collapse) ? '</span>' : '';
	$out .= '</a>';
	return $out;
}

/**
 *
 */
function set_request_to_session()
{
	global $p_role, $p_user, $p_schema, $access_level, $access_session;
	global $access_ary;

	$access_level = $access_session;
	$level_ary = array_flip($access_ary);
	$p_role = $level_ary[$access_level];
	$p_user = ($access_level < 2) ? $_SESSION['id'] : false;

	if ($p_role == 'guest' && isset($_SESSION['interlets']))
	{
		$p_user = $_SESSION['interlets']['id'];
		$p_schema = $_SESSION['interlets']['schema'];
	}
}

/**
 * generate session url
 */
function generate_url($entity = '', $params = '')
{
	global $rootpath, $alert;

	if (is_array($params))
	{
		if ($alert->is_set())
		{
			$params['a'] = '1';
		}
		$params = http_build_query($params);
	}
	else
	{
		if ($alert->is_set())
		{
			$params .= ($params == '') ? 'a=1' : '&a=1';
		}
	}

	$q = get_session_query_param();
	$params = ($params == '') ? (($q == '') ? '' : '?' . $q) : '?' . $params . (($q == '') ? '' : '&' . $q);

	return $rootpath . $entity . '.php' . $params;
}

/**
 * get session query param
 */
function get_session_query_param($return_ary = false)
{
	global $p_role, $p_user, $p_schema, $access_level;
	static $ary, $q;

	if (isset($q))
	{
		return ($return_ary) ? $ary : $q;
	}

	$ary = array();

	if ($p_role != 'anonymous')
	{
		$ary['r'] = $p_role;

		if ($access_level < 2)
		{
			$ary['u'] = $p_user;
		}
		else if ($access_level == 2 && $p_schema)
		{
			$ary['u'] = $p_user;
			$ary['s'] = $p_schema;
		}
	}

	$q = http_build_query($ary);

	return ($return_ary) ? $ary : $q;
}

/**
 *
 */
function redirect_index()
{
	header ('Location: ' . generate_url('index'));
	exit;
}

/**
 *
 */
function redirect_login()
{
	global $rootpath;
	$location = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$get = $_GET;
	unset($get['u'], $get['s'], $get['r']);
	$query_string = http_build_query($get);
	$location .= ($query_string == '') ? '' : '?' . $query_string;
	header('Location: ' . $rootpath . 'login.php?location=' . urlencode($location));
	exit;
}

/**
 *
 */
function redirect($location = false)
{
	if ($location)
	{
		parse_str(parse_url($location, PHP_URL_QUERY), $get);
	}
	else
	{
		$location = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$get = $_GET;
	}
	unset($get['u'], $get['s'], $get['r']);
	$get = http_build_query($get);
	$q = get_session_query_param();
	$get = ($get == '') ? (($q == '') ? '' : '?' . $q) : '?' . $get . (($q == '') ? '' : '&' . $q);
	header('Location: ' . $location . $get);
	exit;
}

/**
 *
 */

function link_user($user, $render = null, $link = true, $show_id = false)
{
	global $rootpath;
	$user = (is_array($user)) ? $user : readuser($user);
	$str = (isset($render)) ? $user[$render] : $user['letscode'] . ' ' . $user['name'];
	$str = ($link) ? aphp('users', 'id=' . $user['id'], ($str == '') ? array('<i>** leeg **</i>') : $str) : (($str == '') ? '<i>** leeg **</i>' : $str);
	$str = ($show_id) ? $str . ' (id: ' . $user['id'] . ')' : $str;
	return $str;
}

/*
 *
 */

function readconfigfromdb($key)
{
    global $db, $schema, $redis;
    global $elas_mongo, $elas_heroku_config;
    static $cache;

	if (isset($cache[$schema][$key]))
	{
		return $cache[$schema][$key];
	}

	$redis_key = $schema . '_config_' . $key;

	if ($redis->exists($redis_key))
	{
		return $cache[$schema][$key] = $redis->get($redis_key);
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
		$cache[$schema][$key] = $value;
	}

	return $value;
}

/**
 * read config from other schemas
 */
function readconfigfromschema($key, $schema)
{
    global $db, $redis;

	$redis_key = $schema . '_config_' . $key;

	if ($redis->exists($redis_key))
	{
		return $redis->get($redis_key);
	}

	$value = $db->fetchColumn('SELECT value FROM ' . $schema . '.config WHERE setting = ?', array($key));

	if (isset($value))
	{
		$redis->set($redis_key, $value);
		$redis->expire($redis_key, 2592000);
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
function readuser($id, $refresh = false, $remote_schema = false)
{
    global $db, $schema, $redis, $elas_mongo;
    static $cache;

	if (!$id)
	{
		return array();
	}

	$s = ($remote_schema) ?: $schema;

	$redis_key = $s . '_user_' . $id;

	if (!$refresh)
	{
		if (isset($cache[$s][$id]))
		{
			return $cache[$s][$id];
		}

		if ($redis->exists($redis_key))
		{
			return $cache[$s][$id] = unserialize($redis->get($redis_key));
		}
	}

	$user = $db->fetchAssoc('SELECT * FROM ' . $s . '.users WHERE id = ?', array($id));

	if (!is_array($user))
	{
		return array();
	}

	$elas_mongo->connect();

	$remote_users = $s . '_users';
	$users = ($remote_schema) ? $elas_mongo->get_client()->$remote_users : $elas_mongo->users;
	$ary = $users->findOne(array('id' => (int) $id));
	$user += (is_array($ary)) ? $ary : array();

	if (isset($user))
	{
		$redis->set($redis_key, serialize($user));
		$redis->expire($redis_key, 2592000);
		$cache[$s][$id] = $user;
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
function render_select_options($option_ary, $selected, $print = true)
{
	$str = '';

	foreach ($option_ary as $key => $value)
	{
		$str .= '<option value="' . $key . '"';
		$str .= ($key == $selected) ? ' selected="selected"' : '';
		$str .= '>' . htmlspecialchars($value, ENT_QUOTES) . '</option>';
	}

	if ($print)
	{
		echo $str;
	}

	return $str;
}

/**
 *
 */
function get_schemas_domains($http = false)
{
	global $db;

	$schemas = $domains = array();

	$schemas_db = ($db->fetchAll('select schema_name from information_schema.schemata')) ?: array();
	$schemas_db = array_map(function($row){ return $row['schema_name']; }, $schemas_db);
	$schemas_db = array_fill_keys($schemas_db, true);

	foreach ($_ENV as $key => $s)
	{
		if (strpos($key, 'SCHEMA_') !== 0 || (!isset($schemas_db[$s])))
		{
			continue;
		}

		$domain = str_replace('SCHEMA_', '', $key);
		$domain = str_replace('____', ':', $domain);
		$domain = str_replace('___', '-', $domain);
		$domain = str_replace('__', '.', $domain);
		$domain = strtolower($domain);
		$domain = (($http) ? 'http://' : '') . $domain;

		$schemas[$domain] = $s;
		$domains[$s] = $domain;
	}

	return array($schemas, $domains);
}

/**
 *
 */
function autominlimit_queue($from_id, $to_id, $amount, $remote_schema = null)
{
	global $redis, $schema;

	$key = (isset($remote_schema)) ? $remote_schema : $schema;
	$key = $key . '_autominlimit_queue';

	$ary = $redis->get($key);

	$ary = ($ary) ? unserialize($ary) : array();

	$ary[] = array(
		'from_id'	=> $from_id,
		'to_id'		=> $to_id,
		'amount'	=> $amount,
	);

	$redis->set($key, serialize($ary));
	$redis->expire($key, 86400);
}

/**
 *
 */
function etag_buffer($content)
{
	global $post, $redis;

	if ($post)
	{
		return $content;
	}

	$etag = crc32($content);

	header('Cache-Control: private, no-cache');
	header('Expires:');
	header('Vary: If-None-Match',false);
	if ($content != '')
	{
		header('Etag: "' . $etag . '"');
	}

    $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ?
        trim(stripslashes($_SERVER['HTTP_IF_NONE_MATCH']), '"') :
        false ;

	if ($if_none_match == $etag && $content)
	{
		http_response_code(304);
		return '';
	}

	return $content;
}
