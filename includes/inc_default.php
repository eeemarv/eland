<?php

if(!isset($rootpath))
{
	$rootpath = '';
}

ob_start('etag_buffer');

$s3_res = getenv('S3_RES') ?: die('Environment variable S3_RES S3 bucket for resources not defined.');
$s3_img = getenv('S3_IMG') ?: die('Environment variable S3_IMG S3 bucket for images not defined.');
$s3_doc = getenv('S3_DOC') ?: die('Environment variable S3_DOC S3 bucket for documents not defined.');

$s3_res_url = 'http://' . $s3_res;
$s3_img_url = 'http://' . $s3_img;
$s3_doc_url = 'http://' . $s3_doc;

header('Access-Control-Allow-Origin: ' . $s3_res_url . ', ' . $s3_img_url . ', ' . $s3_doc_url);

$s3_res_url .= '/';
$s3_img_url .= '/';
$s3_doc_url .= '/';

$typeahead_thumbprint_version = getenv('TYPEAHEAD_THUMBPRINT_VERSION') ?: ''; 

$script_name = ltrim($_SERVER['SCRIPT_NAME'], '/');
$script_name = str_replace('.php', '', $script_name);

$app_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
$host = $_SERVER['SERVER_NAME'];
$base_url = $app_protocol . $host;
$host_id = substr($host, 0, strpos($host, '.'));

$overall_domain = getenv('OVERALL_DOMAIN');

$post = ($_SERVER['REQUEST_METHOD'] == 'GET') ? false : true;

$cdn_bootstrap_css = getenv('CDN_BOOTSTRAP_CSS') ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css';
$cdn_bootstrap_js = getenv('CDN_BOOTSTRAP_JS') ?: '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js';
$cdn_fontawesome = getenv('CDN_FONTAWESOME') ?: '//maxcdn.bootstrapcdn.com/font-awesome/4.6.1/css/font-awesome.min.css';

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
$cdn_images_loaded = (getenv('CDN_IMAGES_LOADED')) ?: '//cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/3.2.0/imagesloaded.pkgd.min.js';

$cdn_leaflet_css = (getenv('CDN_LEAFLET_CSS')) ?: 'http://cdn.leafletjs.com/leaflet/v0.7.7/leaflet.css';
$cdn_leaflet_js = (getenv('CDN_LEAFLET_JS')) ?: 'http://cdn.leafletjs.com/leaflet/v0.7.7/leaflet.js';

$cdn_leaflet_label_css = (getenv('CDN_LEAFLET_LABEL_CSS')) ?: 'https://api.mapbox.com/mapbox.js/plugins/leaflet-label/v0.2.1/leaflet.label.css';
$cdn_leaflet_label_js = (getenv('CDN_LEAFLET_LABEL_JS')) ?: 'https://api.mapbox.com/mapbox.js/plugins/leaflet-label/v0.2.1/leaflet.label.js';

$mapbox_token = getenv('MAPBOX_TOKEN');

require_once $rootpath . 'vendor/autoload.php';

// Connect to Redis
$redis_url = getenv('REDIS_URL') ?: getenv('REDISCLOUD_URL');

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

$eland_config = [
	'users_can_edit_username'	=> ['0', 'Gebruikers kunnen zelf hun gebruikersnaam aanpassen [0, 1]'],
	'users_can_edit_fullname'	=> ['0', 'Gebruikers kunnen zelf hun volledige naam (voornaam + achternaam) aanpassen [0, 1]'],
	'registration_en'			=> ['0', 'Inschrijvingsformulier ingeschakeld [0, 1]'],
	'forum_en'					=> ['0', 'Forum ingeschakeld [0, 1]'],
	'css'						=> ['0', 'Extra stijl: url van .css bestand (Vul 0 in wanneer niet gebruikt)'],
	'msgs_days_default'			=> ['365', 'Standaard geldigheidsduur in aantal dagen van vraag en aanbod.'],
	'balance_equilibrium'		=> ['0', 'Het uitstapsaldo voor actieve leden. Het saldo van leden met status uitstapper kan enkel bewegen in de richting van deze instelling.'],
];

$top_right = '';
$top_buttons = '';

$role_ary = [
	'admin'		=> 'Admin',
	'user'		=> 'User',
	//'guest'		=> 'Guest', //is not a primary role, but a speudo role
	'interlets'	=> 'Interlets',
];

$status_ary = [
	0	=> 'Gedesactiveerd',
	1	=> 'Actief',
	2	=> 'Uitstapper',
	//3	=> 'Instapper',    // not used in selector
	//4 => 'Secretariaat, // not used
	5	=> 'Info-pakket',
	6	=> 'Info-moment',
	7	=> 'Extern',
];

$access_ary = [
	'admin'		=> 0,
	'user'		=> 1,
	'guest'		=> 2,
	'anonymous'	=> 3,
];

$allowed_interlets_landing_pages = [
	'index'			=> true,
	'messages'		=> true,
	'users'			=> true,
	'transactions'	=> true,
	'news'			=> true,
	'docs'			=> true,
];

/*
 * check if we are on the request hosting url.
 */
$key_host_env = str_replace(['.', '-'], ['__', '___'], strtoupper($host));

if ($script_name == 'index' && getenv('HOSTING_FORM_' . $key_host_env))
{
	$page_access = 'anonymous';
	$hosting_form = true;
	return;
}

/*
 * permanent redirects
 */

if ($redirect = getenv('REDIRECT_' . $key_host_env))
{
	header('HTTP/1.1 301 Moved Permanently');
	header('Location: ' . $app_protocol . $redirect . $_SERVER['REQUEST_URI']);
	exit;
}

/**
 * database connection
 * (search path not set yet)
 */

$db = \Doctrine\DBAL\DriverManager::getConnection([
	'url' => getenv('DATABASE_URL'),
], new \Doctrine\DBAL\Configuration());

/**
 * Get all eland schemas and domains
 */

$schemas = $hosts = [];

$schemas_db = ($db->fetchAll('select schema_name from information_schema.schemata')) ?: [];
$schemas_db = array_map(function($row){ return $row['schema_name']; }, $schemas_db);
$schemas_db = array_fill_keys($schemas_db, true);

foreach ($_ENV as $key => $s)
{
	if (strpos($key, 'SCHEMA_') !== 0 || (!isset($schemas_db[$s])))
	{
		continue;
	}

	$h = str_replace(['SCHEMA_', '___', '__'], ['', '-', '.'], $key);
	$h = strtolower($h);

	if (!strpos($h, '.' . $overall_domain))
	{
		$h .= '.' . $overall_domain;
	}

	if (strpos($h, 'localhost') === 0)
	{
		continue;
	}

	$schemas[$h] = $s;
	$hosts[$s] = $h;
}

/*
 * Set schema
 *
 * + schema is the schema in the postgres database
 * + schema is prefix of the image files.
 * + schema name is prefix of keys in Redis.
 *
 */

$schema = $schemas[$host];

if (!$schema)
{
	http_response_code(404);
	include $rootpath. 'tpl/404.html';
	exit;
}

/**
 * alerts
**/

require_once $rootpath . 'includes/inc_alert.php';

$alert = new alert();

/**
 * start session
 */

require_once $rootpath . 'includes/redis_session.php';

$redis_session = new redis_session($redis);
session_set_save_handler($redis_session);
session_name('eland');
session_set_cookie_params(0, '/', '.' . $overall_domain);
session_start();

/** user **/

$p_role = (isset($_GET['r'])) ? $_GET['r'] : 'anonymous';
$p_user = (isset($_GET['u'])) ? $_GET['u'] : false;
$p_schema = (isset($_GET['s'])) ? $_GET['s'] : false;

$s_schema = ($p_schema) ?: $schema;
$s_id = ctype_digit($p_user) ? $p_user : false;
$s_accountrole = isset($access_ary[$p_role]) ? $p_role : 'anonymous';

$s_group_self = ($s_schema == $schema) ? true : false;

/** access user **/

$logins = isset($_SESSION['logins']) ? $_SESSION['logins'] : [];

$s_master = $s_elas_guest = false;

if (count($logins))
{
	error_log('logins: ' . http_build_query($logins));
}
else
{
	error_log('no logins');
}

/**
 *
 */

if (!count($logins))
{
	if ($s_accountrole != 'anonymous')
	{
		redirect_login();
	}
}

if (!isset($logins[$s_schema]))
{
	if ($s_accountrole != 'anonymous')
	{
		redirect_login();
	}
}
else if ($logins[$s_schema] != $s_id || !$s_id)
{
	$s_id = $logins[$s_schema];

	if (ctype_digit((string) $s_id))
	{
		$location = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$get = $_GET;

		unset($get['u'], $get['s'], $get['r']);

		$session_user = readuser($s_id, false, $s_schema);

		$get['r'] = $session_user['accountrole'];
		$get['u'] = $s_id;

		if ($s_schema != $schema)
		{
			$get['s'] = $s_schema;
		}

		$get = http_build_query($get);
		header('Location: ' . $location . '?' . $get);
		exit;
	}

	redirect_login();
}
else if (ctype_digit((string) $s_id))
{
	$session_user = readuser($s_id, false, $s_schema);

	if ($s_schema != $schema && $s_accountrole != 'guest')
	{
		$location = $app_protocol . $hosts[$s_schema] . '/index.php?r=';
		$location .= $session_user['accountrole'] . '&u=' . $s_id;
		header('Location: ' . $location);
		exit;
	}

	if ($access_ary[$session_user['accountrole']] > $access_ary[$s_accountrole])
	{
		redirect_index();
	}
}
else if ($s_id == 'elas')
{
	if ($s_accountrole != 'guest' || !$s_group_self)
	{
		redirect_login();
	}

	$s_id = 'elas';
	$s_elas_guest = true;
}
else if ($s_id == 'master')
{
	$s_id = 'master';
	$s_master = true;
}
else
{
	redirect_login();
}

/** page access **/

if (!isset($page_access))
{
	http_response_code(500);
	include $rootpath . 'tpl/500.html';
	exit;
}

switch ($s_accountrole)
{
	case 'anonymous':

		if ($page_access != 'anonymous')
		{
			redirect_login();
		}

		break;

	case 'guest':

		if ($page_access != 'guest')
		{
			redirect_index();
		}

		break;

	case 'user':

		if (!($page_access == 'user' || $page_access == 'guest'))
		{
			redirect_index();
		}

		break;

	case 'admin':

		if ($page_access == 'anonymous')
		{
			redirect_index();
		}

		break;

	default:

		redirect_login();

		break;
}

/**
 * access control rendering labels and selectors
 */

require_once $rootpath . 'includes/access_control.php';

$access_control = new access_control();

/**
 * some vars
 **/

$access_level = $access_ary[$s_accountrole];

$s_admin = ($s_accountrole == 'admin') ? true : false;
$s_user = ($s_accountrole == 'user') ? true : false;
$s_guest = ($s_accountrole == 'guest') ? true : false;
$s_anonymous = ($s_admin || $s_user || $s_guest) ? false : true;

$errors = [];

/**
 * check access to groups
 **/

$elas_interlets_groups = get_elas_interlets_groups();
$eland_interlets_groups = get_eland_interlets_groups();

if ($s_schema
	&& $page_access != 'anonymous'
	&& !$s_group_self
	&& !$eland_interlets_groups[$schema])
{
	header('Location: ' . generate_url('index', [], $s_schema));
	exit;
}

if ($page_access != 'anonymous' && !$s_admin && readconfigfromdb('maintenance'))
{
	include $rootpath . 'tpl/maintenance.html';
	exit;
}

/**
 * 
 */
require_once $rootpath . 'includes/mdb.php';

$mdb = new mdb($schema);

require_once $rootpath . 'includes/inc_eventlog.php';

// default timezone to Europe/Brussels

date_default_timezone_set((getenv('TIMEZONE')) ?: 'Europe/Brussels');

$schemaversion = 31000;  // no new versions anymore, release file is not read anymore.

// set search path

$db->exec('set search_path to ' . ($schema) ?: 'public');

/* some more vars */

$systemname = readconfigfromdb('systemname');
$systemtag = readconfigfromdb('systemtag');
$currency = readconfigfromdb('currency');
$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

/* view (global for all groups) */

$inline = (isset($_GET['inline'])) ? true : false;

$view = (isset($_GET['view'])) ? $_GET['view'] : false;

$view_users = (isset($_SESSION['view']['users'])) ? $_SESSION['view']['users'] : 'list';
$view_messages = (isset($_SESSION['view']['messages'])) ? $_SESSION['view']['messages'] : 'extended';
$view_news = (isset($_SESSION['view']['news'])) ? $_SESSION['view']['news'] : 'extended';

if ($view || $inline)
{
	if ($script_name == 'users' && $view != $view_users)
	{
		$view_users = ($view) ?: $view_users;
		$_SESSION['view']['users'] = $view = $view_users;
	}
	else if ($script_name == 'messages' && $view != $view_messages)
	{
		$view_messages = ($view) ?: $view_messages;
		$_SESSION['view']['messages'] = $view = $view_messages;
	}
	else if ($script_name == 'news' && $view != $view_news)
	{
		$view_news = ($view) ?: $view_news;
		$_SESSION['view']['news'] = $view = $view_news;
	}
}

/**
 * remember adapted role in own group (for links to own group)
 */
if (!$s_anonymous)
{
	if ($session_user['accountrole'] == 'admin' || $session_user['accountrole'] == 'user')
	{
		if (isset($logins[$schema]) && $s_group_self)
		{
			$_SESSION['roles'][$schema] = $s_accountrole;
		}

		$s_user_params_own_group = [
			'r' => $_SESSION['roles'][$s_schema],
			'u'	=> $s_id,
		];
	}
	else
	{
		$s_user_params_own_group = [];
	}
}

/** welcome message **/

if (isset($_GET['welcome']) && $s_guest)
{
	$msg = '<strong>Welkom bij ' . $systemname . '</strong><br>';
	$msg .= 'Waardering bij ' . $systemname . ' gebeurt met \'' . $currency . '\'. ';
	$msg .= readconfigfromdb('currencyratio') . ' ' . $currency;
	$msg .= ' stemt overeen met 1 LETS uur.<br>';

	if ($s_schema)
	{
		$msg .= 'Je kan steeds terug naar je eigen groep via het menu <strong>Groep</strong> ';
		$msg .= 'boven in de navigatiebalk.';
	}
	else
	{
		$msg .= 'Je bent ingelogd als LETS-gast, je kan informatie ';
		$msg .= 'raadplegen maar niets wijzigen. Transacties moet je ';
		$msg .= 'ingeven in de installatie van je eigen groep.';
	}

	$alert->info($msg);
}

/**************** FUNCTIONS ***************/
/**
 *
 */
function clear_interlets_groups_cache()
{
	global $redis, $s_schema, $schemas;

	$redis->del($s_schema . '_elas_interlets_groups');

	foreach ($schemas as $s)
	{
		$redis->del($s . '_eland_interlets_groups');
	}
}

/**
 *
 */
function get_eland_interlets_groups($refresh = false, $sch = false)
{
	global $redis, $db, $schemas, $hosts, $base_url, $app_protocol, $s_schema;

	if (!$s_schema)
	{
		return [];
	}

	$sch = ($sch) ?: $s_schema;

	$redis_key = $sch . '_eland_interlets_groups';

	if (!$refresh && $redis->exists($redis_key))
	{
		$redis->expire($redis_key, 3600);
		return json_decode($redis->get($redis_key), true);
	}

	$interlets_hosts = $interlets_accounts_schemas = [];

	$st = $db->prepare('select g.url, u.id
		from ' . $sch . '.letsgroups g, ' . $sch . '.users u
		where g.apimethod = \'elassoap\'
			and u.letscode = g.localletscode
			and u.letscode <> \'\'
			and u.accountrole = \'interlets\'
			and u.status in (1, 2, 7)');

	$st->execute();

	while($row = $st->fetch())
	{
		$h = get_host($row['url']);

		if (isset($schemas[$h]))
		{
			$interlets_hosts[] = $h;

			$interlets_accounts_schemas[$row['id']] = $schemas[$h];
		}
	}

	// cache interlets account ids for user interlets linking. (in transactions)
	$redis_key_interlets_accounts = $sch . '_interlets_accounts_schemas';
	$redis->set($redis_key_interlets_accounts, json_encode($interlets_accounts_schemas));
	$redis->expire($redis_key_interlets_accounts, 86400);

	$s_url = $app_protocol . $hosts[$sch];

	$eland_interlets_groups = [];

	foreach ($interlets_hosts as $h)
	{
		$s = $schemas[$h];

		$url = $db->fetchColumn('select g.url
			from ' . $s . '.letsgroups g, ' . $s . '.users u
			where g.apimethod = \'elassoap\'
				and u.letscode = g.localletscode
				and u.letscode <> \'\'
				and u.status in (1, 2, 7)
				and u.accountrole = \'interlets\'
				and g.url = ?', [$s_url]);

		if (!$url)
		{
			continue;
		}

		$eland_interlets_groups[$s] = $h;
	}

	$redis->set($redis_key, json_encode($eland_interlets_groups));
	$redis->expire($redis_key, 3600);

	return $eland_interlets_groups;
}

/**
 *
 */
function get_elas_interlets_groups($refresh = false)
{
	global $redis, $db, $schemas, $base_url, $app_protocol, $s_schema;

	if (!$s_schema)
	{
		return [];
	}

	$redis_key = $s_schema . '_elas_interlets_groups';

	if (!$refresh && $redis->exists($redis_key))
	{
		$redis->expire($redis_key, 3600);
		return json_decode($redis->get($redis_key), true);
	}

	$elas_interlets_groups = [];

	$st = $db->prepare('select g.id, g.groupname, g.url
		from ' . $s_schema . '.letsgroups g, ' . $s_schema . '.users u
		where g.apimethod = \'elassoap\'
			and u.letscode = g.localletscode
			and g.groupname <> \'\'
			and g.url <> \'\'
			and g.myremoteletscode <> \'\'
			and g.remoteapikey <> \'\'
			and g.presharedkey <> \'\'
			and u.letscode <> \'\'
			and u.name <> \'\'
			and u.accountrole = \'interlets\'
			and u.status in (1, 2, 7)');

	$st->execute();

	while($row = $st->fetch())
	{
		$h = get_host($row['url']);

		if (!(isset($schemas[$h])))
		{
			$elas_interlets_groups[$row['id']] = $row;
		}
	}

	$redis->set($redis_key, json_encode($elas_interlets_groups));
	$redis->expire($redis_key, 3600);

	return $elas_interlets_groups;
}

/*
 * create link within eland with query parameters depending on user and role
 */

function aphp(
	$entity = '',
	$params = [],
	$label = '*link*',
	$class = false,
	$title = false,
	$fa = false,
	$collapse = false,
	$attr = false,
	$sch = false)
{
	$out = '<a href="' .  generate_url($entity, $params, $sch) . '"';
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
	$out .= htmlspecialchars($label, ENT_QUOTES);
	$out .= ($collapse) ? '</span>' : '';
	$out .= '</a>';
	return $out;
}

/**
 * generate url
 */
function generate_url($entity = 'index', $params = [], $sch = false)
{
	global $rootpath, $alert, $hosts, $app_protocol;

	if ($alert->is_set())
	{
		$params['a'] = '1';
	}

	$params = array_merge($params, get_session_query_param($sch));

	$params = http_build_query($params);

	$params = ($params) ? '?' . $params : '';

	$path = ($sch) ? $app_protocol . $hosts[$sch] . '/' : $rootpath;

	return $path . $entity . '.php' . $params;
}

/**
 * get session query param
 */
function get_session_query_param($sch = false)
{
	global $p_role, $p_user, $p_schema, $access_level;
	global $s_user_params_own_group, $s_id, $s_schema;
	static $ary;

	if ($sch)
	{
		if ($sch == $s_schema)
		{
			return  $s_user_params_own_group;
		}

		if ($s_schema)
		{
			$param_ary = ['r' => 'guest', 'u' => $s_id, 's' => $s_schema]; 

			return $param_ary;
		}

		return ['r' => 'guest'];
	}

	if (isset($ary))
	{
		return $ary;
	}

	$ary = [];

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

	return $ary;
}

/**
 *
 */
function redirect_index()
{
	global $p_role, $p_user, $p_schema, $access_level, $access_session;
	global $s_id, $s_accountrole, $s_schema;

	$access_level = $access_session;

	$p_schema = $s_schema;
	$p_user = $s_id;
	$p_role = $s_accountrole;

	header('Location: ' . generate_url('index'));
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

function link_user($user, $sch = false, $link = true, $show_id = false, $field = false)
{
	global $rootpath;
	$user = (is_array($user)) ? $user : readuser($user, false, $sch);
	$str = ($field) ? $user[$field] : $user['letscode'] . ' ' . $user['name'];
	$str = ($str == '' || $str == ' ') ? '<i>** leeg **</i>' : htmlspecialchars($str, ENT_QUOTES);
	if ($link)
	{
		$out = '<a href="';
		$out .= generate_url('users', ['id' => $user['id']], $sch);
		$out .= '">' . $str . '</a>';
	}
	else
	{
		$out = $str;
	}
	$out .= ($show_id) ? ' (id: ' . $user['id'] . ')' : '';
	return $out;
}

/*
 *
 */

function readconfigfromdb($key, $sch = null)
{
    global $db, $schema, $redis;
    global $mdb, $eland_config;
    static $cache;

    if (!isset($sch))
    {
		$sch = $schema;
	}

	if (isset($cache[$sch][$key]))
	{
		return $cache[$sch][$key];
	}

	$redis_key = $sch . '_config_' . $key;

	if ($redis->exists($redis_key))
	{
		return $cache[$sch][$key] = $redis->get($redis_key);
	}

	if (isset($eland_config[$key]))
	{
		$mclient = $mdb->connect()->get_client();
		$settings = $sch . '_settings';
		$s = $mclient->$settings->findOne(['name' => $key]);
		$value = (isset($s['value'])) ? $s['value'] : $eland_config[$key][0];
	}
	else
	{
		$value = $db->fetchColumn('SELECT value FROM ' . $sch . '.config WHERE setting = ?', [$key]);
	}

	if (isset($value))
	{
		$redis->set($redis_key, $value);
		$redis->expire($redis_key, 2592000);
		$cache[$sch][$key] = $value;
	}

	return $value;
}

/**
 *
 */
function readuser($id, $refresh = false, $remote_schema = false)
{
    global $db, $schema, $redis, $mdb;
    static $cache;

	if (!$id)
	{
		return [];
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

	$user = $db->fetchAssoc('SELECT * FROM ' . $s . '.users WHERE id = ?', [$id]);

	if (!is_array($user))
	{
		return [];
	}

	$mdb->connect();

	$remote_users = $s . '_users';
	$users = ($remote_schema) ? $mdb->get_client()->$remote_users : $mdb->users;
	$ary = $users->findOne(['id' => (int) $id]);
	$user += (is_array($ary)) ? $ary : [];

	if (isset($user))
	{
		$redis->set($redis_key, serialize($user));
		$redis->expire($redis_key, 2592000);
		$cache[$s][$id] = $user;
	}

	return $user;
}

/**
 *
 */

function mail_q($mail = [], $priority = false)
{
	global $schema, $redis;

	// only the interlets transactions receiving side has a different schema

	$mail['schema'] = isset($mail['schema']) ? $mail['schema'] : $schema;

	if (!readconfigfromdb('mailenabled'))
	{
		$m = 'Mail functions are not enabled. ' . "\n";
		echo $m;
		log_event('mail', $m);
		return $m;
	}

	if (!isset($mail['subject']) || $mail['subject'] == '')
	{
		$m = 'Mail "subject" is missing.';
		log_event('mail', $m);
		return $m;
	}

	if ((!isset($mail['text']) || $mail['text'] == '')
		&& (!isset($mail['html']) || $mail['html'] == ''))
	{
		$m = 'Mail "body" (text or html) is missing.';
		log_event('mail', $m);
		return $m;
	}

	if (!isset($mail['to']) || !$mail['to'])
	{
		$m = 'Mail "to" is missing for "' . $mail['subject'] . '"';
		log_event('mail', $m);
		return $m;
	}

	$mail['to'] = getmailadr($mail['to']);

	if (!count($mail['to']))
	{
		$m = 'error: mail without "to" | subject: ' . $mail['subject'];
		log_event('mail', $m);
		return $m;
	} 

	if (isset($mail['reply_to']))
	{
		$mail['reply_to'] = getmailadr($mail['reply_to']);

		if (!count($mail['reply_to']))
		{
			log_event('mail', 'error: invalid "reply to" : ' . $mail['subject']);
			unset($mail['reply_to']);
		}

		$mail['from'] = getmailadr('from', $mail['schema']);
	}
	else
	{
		$mail['from'] = getmailadr('noreply', $mail['schema']);
	}

	if (!count($mail['from']))
	{
		$m = 'error: mail without "from" | subject: ' . $mail['subject'];
		log_event('mail', $m);
		return $m;
	}

	if (isset($mail['cc']))
	{
		$mail['cc'] = getmailadr($mail['cc']);

		if (!count($mail['cc']))
		{
			log_event('mail', 'error: invalid "reply to" : ' . $mail['subject']);
			unset($mail['cc']);
		}
	}

	$systemtag = readconfigfromdb('systemtag', $mail['schema']);

	$mail['subject'] = '[' . $systemtag . '] ' . $mail['subject'];

	$queue = ($priority) ? '1' : '0';

	if ($redis->lpush('mail_q' . $queue, json_encode($mail)))
	{
		$reply = (isset($mail['reply_to'])) ? ' reply-to: ' . json_encode($mail['reply_to']) : '';

		log_event('mail', 'Mail in queue, subject: ' .
			$mail['subject'] . ', from : ' .
			json_encode($mail['from']) . ' to : ' . json_encode($mail['to']) . $reply, $mail['schema']);
	}
}

/*
 * param string mail addr | [string.]int [schema.]user id | array
 * param string sending_schema
 * return array
 */

function getmailadr($m, $sending_schema = false)
{
	global $schema, $db, $s_admin;

	$sch = ($sending_schema) ?: $schema;

	if (!is_array($m))
	{
		$m = explode(',', $m);
	}

	$out = [];

	foreach ($m as $in)
	{
		$in = trim($in);

		$remote_id = strrchr($in, '.');
		$remote_schema = str_replace($remote_id, '', $in);
		$remote_id = trim($remote_id, '.');

		if (in_array($in, ['admin', 'newsadmin', 'support']))
		{
			$ary = explode(',', readconfigfromdb($in));
			$systemname = readconfigfromdb('systemname');

			foreach ($ary as $mail)
			{
				$mail = trim($mail);

				if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
				{
					log_event('mail', 'error: invalid ' . $in . ' mail address : ' . $mail);
					continue;
				}

				$out[$mail] = $systemname;
			}
		}
		else if (in_array($in, ['from', 'noreply']))
		{
			$mail = getenv('MAIL_' . strtoupper($in) . '_ADDRESS');
			$mail = trim($mail);
			$systemname = readconfigfromdb('systemname', $sch);

			if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
			{
				log_event('mail', 'error: invalid ' . $in . ' mail address : ' . $mail);
				continue;
			}

			$out[$mail] = $systemname;
		}
		else if (ctype_digit((string) $in))
		{
			$status_sql = ($s_admin) ? '' : ' and u.status in (1,2)';

			$st = $db->prepare('select c.value, u.name, u.letscode
				from contact c,
					type_contact tc,
					users u
				where c.id_type_contact = tc.id
					and c.id_user = ?
					and c.id_user = u.id
					and tc.abbrev = \'mail\''
					. $status_sql);

			$st->bindValue(1, $in);
			$st->execute();

			while ($row = $st->fetch())
			{
				$mail = trim($row['value']);

				if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
				{
					log_event('mail', 'error: invalid mail address : ' . $mail . ', user id: ' . $in);
					continue;
				}

				$out[$mail] = $row['letscode'] . ' ' . $row['name'];
			}
		}
		else if (ctype_digit((string) $remote_id) && $remote_schema)
		{
			$st = $db->prepare('select c.value, u.name, u.letscode
				from ' . $remote_schema . '.contact c,
					' . $remote_schema . '.type_contact tc,
					' . $remote_schema . '.users u
				where c.id_type_contact = tc.id
					and c.id_user = ?
					and c.id_user = u.id
					and u.status in (1, 2)
					and tc.abbrev = \'mail\'');

			$st->bindValue(1, $remote_id);
			$st->execute();

			while ($row = $st->fetch())
			{
				$mail = trim($row['value']);
				$letscode = trim($row['letscode']);
				$name = trim($row['name']);

				$user = $remote_schema . '.' . $letscode . ' ' . $name;

				if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
				{
					log_event('mail', 'error: invalid mail address from interlets: ' . $mail . ', user: ' . $user);
					continue;
				}

				$out[$mail] = $user;
			}
		}
		else if (filter_var($in, FILTER_VALIDATE_EMAIL))
		{
			$out[] = $in;
		}
		else
		{
			log_event('error: no valid input for mail adr: ' . $in);
		}
	}

	if (!count($out))
	{
		log_event('mail', 'no valid mail adress found for: ' . implode('|', $m));
		return $out;
	} 

	return $out;
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

function generate_form_token($print = true)
{
	global $redis;
	static $token;

	if (!isset($token))
	{
		$token = sha1(microtime() . mt_rand(0, 1000000));
		$key = 'form_token_' . $token;
		$redis->set($key, '1');
		$redis->expire($key, 14400); // 4 hours
	}

	if ($print)
	{
		echo '<input type="hidden" name="form_token" value="' . $token . '">';
	}

	return $token;
}

/**
 * return false|string (error message)
 */

function get_error_form_token()
{
	global $redis, $script_name;

	if (!isset($_POST['form_token']))
	{
		return 'Het formulier bevat geen token';
	}

	$token = $_POST['form_token'];
	$key = 'form_token_' . $token;

	$value = $redis->get($key);

	if (!$value)
	{
		$m = 'Het formulier is verlopen';
		log_event('form_token', $m . ': ' . $script_name);
		return $m;
	}

	if ($value > 1)
	{
		$redis->incr($key);
		$m = 'Een dubbele ingave van het formulier werd voorkomen.';
		log_event('form_token', $m . '(count: ' . $value . ') : ' . $script_name);
		return $m;
	}

	$redis->incr($key);

	return false;
}

/**
*
 */

function get_host($url)
{
	if (is_array($url))
	{
		$url = $url['url'];
	}

	return strtolower(parse_url($url, PHP_URL_HOST));
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

	$ary = ($ary) ? unserialize($ary) : [];

	$ary[] = [
		'from_id'	=> $from_id,
		'to_id'		=> $to_id,
		'amount'	=> $amount,
	];

	$redis->set($key, serialize($ary));
	$redis->expire($key, 86400);
}

/**
 *
 */

function get_typeahead_thumbprint($name = 'users_active', $group_url = false)
{
	global $redis, $base_url, $typeahead_thumbprint_version;

	$group_url = ($group_url) ?: $base_url;

	$redis_key = $group_url . '_typeahead_thumbprint_' . $name;

	$thumbprint = $typeahead_thumbprint_version . $redis->get($redis_key);

	if (!$thumbprint)
	{
		return 'renew-' . crc32(microtime());
	}

	return $thumbprint;
}

/**
 *
 */

function invalidate_typeahead_thumbprint(
	$name = 'users_active',
	$group_url = false,
	$new_thumbprint = false,
	$ttl = 5184000)	// 60 days;
{
	global $redis, $base_url;

	$group_url = ($group_url) ?: $base_url;

	$redis_key = $group_url . '_typeahead_thumbprint_' . $name;

	if ($new_thumbprint)
	{
		if ($new_thumbprint != $redis->get($redis_key))
		{
			$redis->set($redis_key, $new_thumbprint);
			log_event('typeahead', 'new typeahead thumbprint ' . $new_thumbprint . ' for ' . $group_url . ' : ' . $name);
		}

		$redis->expire($redis_key, $ttl);
	}
	else
	{
		$redis->del($redis_key);

		log_event('typeahead', 'typeahead thumbprint deleted for ' . $group_url . ' : ' . $name);
	}
}

/**
 * 
 */
function get_typeahead($name_ary, $group_url = false, $group_id = false)
{
	global $rootpath;

	$out = '';

	if (!is_array($name_ary))
	{
		$name_ary = [$name_ary];
	}

	foreach($name_ary as $name)
	{
		$out .= get_typeahead_thumbprint($name, $group_url) . '|';

		if (strpos($name, 'users_') !== false)
		{
			$status = str_replace('users_', '', $name);
			$out .= $rootpath . 'ajax/typeahead_users.php?status=' . $status;
			$out .= ($group_id) ? '&group_id=' . $group_id : '';
			$out .= '&' . http_build_query(get_session_query_param());
		}
		else
		{
			$out .= $rootpath . 'ajax/typeahead_' . $name . '.php?';
			$out .= http_build_query(get_session_query_param());
		}

		$out .= '|';
	}

	return rtrim($out, '|');
}

/**
 *
 */
function etag_buffer($content)
{
	global $post;

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
