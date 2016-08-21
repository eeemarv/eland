<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();

$app['debug'] = getenv('DEBUG');

$app['redis'] = function () {
	try
	{
		$url = getenv('REDIS_URL') ?: getenv('REDISCLOUD_URL');
		$con = parse_url($url);

		if (isset($con['pass']))
		{
			$con['password'] = $con['pass'];
		}

		$con['scheme'] = 'tcp';

		return new Predis\Client($con);
	}
	catch (Exception $e)
	{
		echo 'Couldn\'t connected to Redis: ';
		echo $e->getMessage();
		exit;
	}
};

$app->register(new Silex\Provider\DoctrineServiceProvider(), [
    'db.options' => [
        'url'   => getenv('DATABASE_URL'),
    ],
]);

$app->register(new Silex\Provider\TwigServiceProvider(), [
	'twig.path' => __DIR__ . '/../views',
	'twig.options'	=> [
		'cache'		=> __DIR__ . '/../cache',
	],
]);

$app->register(new Silex\Provider\MonologServiceProvider(), []);

$app->extend('monolog', function($monolog, $app) {

	$handler = new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG);
	$handler->setFormatter(new \Bramus\Monolog\Formatter\ColoredLineFormatter());
	$monolog->pushHandler($handler);

	$handler = new \Monolog\Handler\RedisHandler($app['redis'], 'monolog_logs', \Monolog\Logger::DEBUG, true, 20);
	$handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
	$monolog->pushHandler($handler);

	$monolog->pushProcessor(new Monolog\Processor\WebProcessor());

	$monolog->pushProcessor(function ($record) use ($app){

		if (isset($app['eland.schema']))
		{
			$record['extra']['schema'] = $app['eland.schema'];
		}

		if (isset($app['eland.session_user']))
		{
			$record['extra']['letscode'] = $app['eland.session_user']['letscode'];
			$record['extra']['user_id'] = $app['eland.session_user']['id'];
		}

		if (isset($app['eland.session_schema']))
		{
			$record['extra']['user_schema'] = $app['eland.session_schema'];
		}

		return $record;
	});

	return $monolog;
});

if(!isset($rootpath))
{
	$rootpath = './';
}

$app['eland.rootpath'] = $rootpath;

$app['eland.protocol'] = $app_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";

ob_start('etag_buffer');

$app['eland.s3_res'] = getenv('S3_RES') ?: die('Environment variable S3_RES S3 bucket for resources not defined.');
$app['eland.s3_img'] = getenv('S3_IMG') ?: die('Environment variable S3_IMG S3 bucket for images not defined.');
$app['eland.s3_doc'] = getenv('S3_DOC') ?: die('Environment variable S3_DOC S3 bucket for documents not defined.');

$header_allow_origin = $app['eland.protocol'] . $app['eland.s3_res'] . ', ';
$header_allow_origin .= $app['eland.protocol'] . $app['eland.s3_img'] . ', ';
$header_allow_origin .= $app['eland.protocol'] . $app['eland.s3_doc'];

header('Access-Control-Allow-Origin: ' . $header_allow_origin);

$app['eland.s3_res_url'] = $app['eland.protocol'] . $app['eland.s3_res'] . '/';
$app['eland.s3_img_url'] = $app['eland.protocol'] . $app['eland.s3_img'] . '/';
$app['eland.s3_doc_url'] = $app['eland.protocol'] . $app['eland.s3_doc'] . '/';

$app['eland.s3'] = function($app){
	return new eland\s3($app['eland.s3_res'], $app['eland.s3_img'], $app['eland.s3_doc']);
};

$app['eland.assets'] = function($app){
	return new eland\assets($app['eland.s3_res_url'], $app['eland.rootpath']);
};

$app['eland.assets']->add(['jquery', 'bootstrap', 'fontawesome', 'footable', 'base.css', 'base.js']);

$script_name = ltrim($_SERVER['SCRIPT_NAME'], '/');
$script_name = str_replace('.php', '', $script_name);

$host = $_SERVER['SERVER_NAME'];
$app['eland.base_url'] = $base_url = $app['eland.protocol'] . $host;

$host_id = substr($host, 0, strpos($host, '.'));

$overall_domain = getenv('OVERALL_DOMAIN');

$post = ($_SERVER['REQUEST_METHOD'] == 'GET') ? false : true;

$app['eland.mapbox_token'] = getenv('MAPBOX_TOKEN');

/*
 * The locale must be installed in the OS for formatting dates.
 */

setlocale(LC_TIME, 'nl_NL.UTF-8');

date_default_timezone_set((getenv('TIMEZONE')) ?: 'Europe/Brussels');

$app['eland.typeahead'] = function($app){

	return new eland\typeahead($app['redis'], $app['monolog'], $app['eland.base_url'], $app['eland.rootpath']);
};

/**
 * vars
 **/

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
 * Get all eland schemas and domains
 */

$schemas = $hosts = [];

$schemas_db = ($app['db']->fetchAll('select schema_name from information_schema.schemata')) ?: [];
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

	echo $app['twig']->render('404.twig');
	exit;
}

$app['eland.alert'] = function ($app){
	return new eland\alert($app['monolog']);
};

$app['eland.pagination'] = function (){
	return new eland\pagination();
};

$app['eland.interlets_groups'] = function ($app) use ($schemas, $hosts, $app_protocol) {
	return new eland\interlets_groups($app['db'], $app['redis'], $schemas, $hosts, $app_protocol);
};



/**
 * start session
 */

session_set_save_handler(new eland\redis_session($app['redis']));
session_name('eland');
session_set_cookie_params(0, '/', '.' . $overall_domain);
session_start();

/*
 * set search path
 */

$app['db']->exec('set search_path to ' . ($schema) ?: 'public');

/** user **/

$p_role = $_GET['r'] ?? 'anonymous';
$p_user = $_GET['u'] ?? false;
$p_schema = $_GET['s'] ?? false;

$s_schema = ($p_schema) ?: $schema;
$s_id = $p_user;
$s_accountrole = isset($access_ary[$p_role]) ? $p_role : 'anonymous';

$s_group_self = ($s_schema == $schema) ? true : false;

/** access user **/

$logins = $_SESSION['logins'] ?? [];

$s_master = $s_elas_guest = false;

/*
if (count($logins))
{
	$app['monolog']->debug('logins: ' . http_build_query($logins));
}
else
{
	$app['monolog']->debug('no logins');
}

$app['monolog']->debug('s_id: ' . $s_id);
*/
/**
 *
 */

if (!count($logins))
{
	if ($s_accountrole != 'anonymous')
	{
		$app['monolog']->debug('redirect a');
		redirect_login();
	}
}

if (!$s_id)
{
	if ($page_access != 'anonymous')
	{
		if (isset($logins[$s_schema]) && ctype_digit((string) $logins[$s_schema]))
		{
			$s_id = $logins[$s_schema];

			$location = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
			$get = $_GET;

			unset($get['u'], $get['s'], $get['r']);

			$session_user = readuser($s_id, false, $s_schema);

			$get['r'] = $session_user['accountrole'];
			$get['u'] = $s_id;

			if (!$s_group_self)
			{
				$get['s'] = $s_schema;
			}

			$app['monolog']->debug('redirect p');

			$get = http_build_query($get);
			header('Location: ' . $location . '?' . $get);
			exit;

		}

		$app['monolog']->debug('redirect b');
		redirect_login();
	}

	if ($s_accountrole != 'anonymous')
	{
		$app['monolog']->debug('redirect c');
		redirect_login();
	}
}
else if (!isset($logins[$s_schema]))
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

		if (!$s_group_self)
		{
			$get['s'] = $s_schema;
		}

		$app['monolog']->debug('redirect d');

		$get = http_build_query($get);
		header('Location: ' . $location . '?' . $get);
		exit;
	}

	$app['monolog']->debug('redirect 1');
	redirect_login();
}
else if (ctype_digit((string) $s_id))
{
	$session_user = readuser($s_id, false, $s_schema);

	if (!$s_group_self && $s_accountrole != 'guest')
	{
		$location = $app_protocol . $hosts[$s_schema] . '/index.php?r=';
		$location .= $session_user['accountrole'] . '&u=' . $s_id;
		header('Location: ' . $location);
		exit;
	}

	if ($access_ary[$session_user['accountrole']] > $access_ary[$s_accountrole])
	{
		$app['monolog']->debug('redirect 2');

		$s_accountrole = $session_user['accountrole'];

		redirect_index();
	}

	if (!($session_user['status'] == 1 || $session_user['status'] == 2))
	{
		$app['monolog']->debug('redirect 2a');
		$_SESSION = [];
		redirect_login();
	}
}
else if ($s_id == 'elas')
{
	if ($s_accountrole != 'guest' || !$s_group_self)
	{
		$app['monolog']->debug('redirect 3');
		redirect_login();
	}

	$s_elas_guest = true;
}
else if ($s_id == 'master')
{
	if (!$s_group_self && $s_accountrole != 'guest')
	{
		$app['monolog']->debug('redirect 3a');

		$location = $app_protocol . $hosts[$s_schema] . '/index.php?r=admin&u=master';
		header('Location: ' . $location);
		exit;
	}

	$s_master = true;
}
else
{
	$app['monolog']->debug('redirect 4');
	redirect_login();
}

/** page access **/

if (!isset($page_access))
{
	http_response_code(500);

	echo $app['twig']->render('500.twig');
	exit;
}

switch ($s_accountrole)
{
	case 'anonymous':

		if ($page_access != 'anonymous')
		{
			$app['monolog']->debug('redirect 5');
			redirect_login();
		}

		break;

	case 'guest':

		if ($page_access != 'guest')
		{
			$app['monolog']->debug('redirect 6');
			redirect_index();
		}

		break;

	case 'user':

		if (!($page_access == 'user' || $page_access == 'guest'))
		{
			$app['monolog']->debug('redirect 7');
			redirect_index();
		}

		break;

	case 'admin':

		if ($page_access == 'anonymous')
		{
			$app['monolog']->debug('redirect 8');
			redirect_index();
		}

		break;

	default:

		$app['monolog']->debug('redirect 9');
		redirect_login();

		break;
}

$app['eland.access_control'] = function($app){
	return new eland\access_control();
};

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

$elas_interlets_groups = $app['eland.interlets_groups']->get_elas($s_schema);
$eland_interlets_groups = $app['eland.interlets_groups']->get_eland($s_schema);

if ($s_group_self && $s_guest)
{
	$elas_interlets_groups = $eland_interlets_groups = [];
}

if ($page_access != 'anonymous'
	&& !$s_group_self
	&& !$eland_interlets_groups[$schema])
{
	header('Location: ' . generate_url('index', [], $s_schema));
	exit;
}

if ($page_access != 'anonymous' && !$s_admin && readconfigfromdb('maintenance'))
{
	echo $app['twig']->render('maintenance.twig');
	exit;
}

/**
 * 
 */

require_once __DIR__ . '/../includes/inc_eventlog.php';

 /**
  *
  */

$app['eland.xdb'] = function ($app){
	return new eland\xdb($app['db'], $app['monolog']);
};

$app['eland.xdb']->init($schema, $s_schema, $s_id);

$app['eland.queue'] = function($app){
	return new eland\queue($app['db'], $app['monolog']);
};

$app['eland.date_format'] = function(){
	return new eland\date_format();
};

$app['eland.form_token'] = function($app){
	return new eland\form_token($app['redis'], $app['monolog']);
};

/* some more vars */

$app['eland.schema'] = $schema;
$app['eland.session_user'] = $session_user ?? [];
$app['eland.session_schema'] = $s_schema;

/*
$app['monolog']->debug('debug.');
$app['monolog']->notice('notice.');
$app['monolog']->info('info.');
$app['monolog']->error('error.');
$app['monolog']->warning('warning.');
$app['monolog']->critical('critical.');
*/
$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

/* view (global for all groups) */

$inline = (isset($_GET['inline'])) ? true : false;

$view = $_GET['view'] ?? false;

$view_users = $_SESSION['view']['users'] ?? 'list';
$view_messages = $_SESSION['view']['messages'] ?? 'extended';
$view_news = $_SESSION['view']['news'] ?? 'extended';

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
	if ($s_master || $session_user['accountrole'] == 'admin' || $session_user['accountrole'] == 'user')
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
	$msg = '<strong>Welkom bij ' . readconfigfromdb('systemname') . '</strong><br>';
	$msg .= 'Waardering bij ' . readconfigfromdb('systemname') . ' gebeurt met \'' . readconfigfromdb('currency') . '\'. ';
	$msg .= readconfigfromdb('currencyratio') . ' ' . readconfigfromdb('currency');
	$msg .= ' stemt overeen met 1 LETS uur.<br>';

	if ($s_elas_guest)
	{
		$msg .= 'Je bent ingelogd als LETS-gast, je kan informatie ';
		$msg .= 'raadplegen maar niets wijzigen. Transacties moet je ';
		$msg .= 'ingeven in de installatie van je eigen groep.';
	}
	else
	{
		$msg .= 'Je kan steeds terug naar je eigen groep via het menu <strong>Groep</strong> ';
		$msg .= 'boven in de navigatiebalk.';
	}

	$app['eland.alert']->info($msg);
}

/**************** FUNCTIONS ***************/

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
	global $rootpath, $app, $hosts, $app_protocol;

	if ($app['eland.alert']->is_set())
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
		$ary['u'] = $p_user;

		if ($access_level == 2 && $p_schema)
		{
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

	if (!$user)
	{
		return '<i>** leeg **</i>';
	}

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
    global $app, $schema;
    static $cache;

	$eland_config_default = [
		'users_can_edit_username'	=> '0',
		'users_can_edit_fullname'	=> '0',
		'registration_en'			=> '0',
		'registration_top_text'		=> '',
		'registration_bottom_text'	=> '',
		'registration_success_text'	=> '',
		'registration_success_url'	=> '',
		'forum_en'					=> '0',
		'css'						=> '0',
		'msgs_days_default'			=> '365',
		'balance_equilibrium'		=> '0',
		'date_format'				=> '%e %b %Y, %H:%M:%S',
	];

    if (!isset($sch))
    {
		$sch = $schema;
	}

	if (isset($cache[$sch][$key]))
	{
		return $cache[$sch][$key];
	}

	$redis_key = $sch . '_config_' . $key;

	if ($app['redis']->exists($redis_key))// && $key != 'date_format')
	{
		return $cache[$sch][$key] = $app['redis']->get($redis_key);
	}

	$row = $app['eland.xdb']->get('setting', $key, $sch);

	if ($row)
	{
		$value = $row['data']['value'];
	}
	else if (isset($eland_config_default[$key]))
	{
		$value = $eland_config_default[$key];
	}
	else
	{
		$value = $app['db']->fetchColumn('select value from ' . $sch . '.config where setting = ?', [$key]);

		$app['eland.xdb']->set('setting', $key, ['value' => $value], $sch);
	}

	if (isset($value))
	{
		$app['redis']->set($redis_key, $value);
		$app['redis']->expire($redis_key, 2592000);
		$cache[$sch][$key] = $value;
	}

	return $value;
}

/**
 *
 */
function readuser($id, $refresh = false, $remote_schema = false)
{
    global $app, $schema;
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

		if ($app['redis']->exists($redis_key))
		{
			return $cache[$s][$id] = unserialize($app['redis']->get($redis_key));
		}
	}

	$user = $app['db']->fetchAssoc('select * from ' . $s . '.users where id = ?', [$id]);

	if (!is_array($user))
	{
		return [];
	}

	$row = $app['eland.xdb']->get('user_fullname_access', $id, $s);

	if ($row)
	{
		$user += ['fullname_access' => $row['data']['fullname_access']];
	}
	else
	{
		$user += ['fullname_access' => 'admin'];

		$app['eland.xdb']->set('user_fullname_access', $id, ['fullname_access' => 'admin'], $s);
	}

	if (isset($user))
	{
		$app['redis']->set($redis_key, serialize($user));
		$app['redis']->expire($redis_key, 2592000);
		$cache[$s][$id] = $user;
	}

	return $user;
}

/**
 *
 */

function mail_q($mail = [], $priority = false)
{
	global $schema, $app;

	// only the interlets transactions receiving side has a different schema

	$mail['schema'] = $mail['schema'] ?? $schema;

	if (!readconfigfromdb('mailenabled'))
	{
		$m = 'Mail functions are not enabled. ' . "\n";
		$app['monolog']->info('mail: ' . $m);
		return $m;
	}

	if (!isset($mail['subject']) || $mail['subject'] == '')
	{
		$m = 'Mail "subject" is missing.';
		$app['monolog']->error('mail: '. $m);
		return $m;
	}

	if ((!isset($mail['text']) || $mail['text'] == '')
		&& (!isset($mail['html']) || $mail['html'] == ''))
	{
		$m = 'Mail "body" (text or html) is missing.';
		$app['monolog']->error('mail: ' . $m);
		return $m;
	}

	if (!isset($mail['to']) || !$mail['to'])
	{
		$m = 'Mail "to" is missing for "' . $mail['subject'] . '"';
		$app['monolog']->error('mail: ' . $m);
		return $m;
	}

	$mail['to'] = getmailadr($mail['to']);

	if (!count($mail['to']))
	{
		$m = 'error: mail without "to" | subject: ' . $mail['subject'];
		$app['monolog']->error('mail: ' . $m);
		return $m;
	} 

	if (isset($mail['reply_to']))
	{
		$mail['reply_to'] = getmailadr($mail['reply_to']);

		if (!count($mail['reply_to']))
		{
			$app['monolog']->error('mail: error: invalid "reply to" : ' . $mail['subject']);
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
		$app['monolog']->error('mail: ' . $m);
		return $m;
	}

	if (isset($mail['cc']))
	{
		$mail['cc'] = getmailadr($mail['cc']);

		if (!count($mail['cc']))
		{
			$app['monolog']->error('mail error: invalid "reply to" : ' . $mail['subject']);
			unset($mail['cc']);
		}
	}

	$mail['subject'] = '[' . readconfigfromdb('systemtag', $mail['schema']) . '] ' . $mail['subject'];

	$error = $app['eland.queue']->set('mail', $mail, ($priority) ? 10 : 0);

	if (!$error)
	{
		$reply = (isset($mail['reply_to'])) ? ' reply-to: ' . json_encode($mail['reply_to']) : '';

		$app['monolog']->info('mail: Mail in queue, subject: ' .
			$mail['subject'] . ', from : ' .
			json_encode($mail['from']) . ' to : ' . json_encode($mail['to']) . $reply, ['schema' => $mail['schema']]);
	}
}

/*
 * param string mail addr | [string.]int [schema.]user id | array
 * param string sending_schema
 * return array
 */

function getmailadr($m, $sending_schema = false)
{
	global $schema, $app, $s_admin;

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

			foreach ($ary as $mail)
			{
				$mail = trim($mail);

				if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
				{
					$app['monolog']->error('mail error: invalid ' . $in . ' mail address : ' . $mail);
					continue;
				}

				$out[$mail] = readconfigfromdb('systemname');
			}
		}
		else if (in_array($in, ['from', 'noreply']))
		{
			$mail = getenv('MAIL_' . strtoupper($in) . '_ADDRESS');
			$mail = trim($mail);

			if (!filter_var($mail, FILTER_VALIDATE_EMAIL))
			{
				$app['monolog']->error('mail error: invalid ' . $in . ' mail address : ' . $mail);
				continue;
			}

			$out[$mail] = readconfigfromdb('systemname', $sch);
		}
		else if (ctype_digit((string) $in))
		{
			$status_sql = ($s_admin) ? '' : ' and u.status in (1,2)';

			$st = $app['db']->prepare('select c.value, u.name, u.letscode
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
					$app['monolog']->error('mail error: invalid mail address : ' . $mail . ', user id: ' . $in);
					continue;
				}

				$out[$mail] = $row['letscode'] . ' ' . $row['name'];
			}
		}
		else if (ctype_digit((string) $remote_id) && $remote_schema)
		{
			$st = $app['db']->prepare('select c.value, u.name, u.letscode
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
					$app['monolog']->error('mail error: invalid mail address from interlets: ' . $mail . ', user: ' . $user);
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
			$app['monolog']->error('mail error: no valid input for mail adr: ' . $in);
		}
	}

	if (!count($out))
	{
		$app['monolog']->error('mail error: no valid mail adress found for: ' . implode('|', $m));
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
function autominlimit_queue($from_id, $to_id, $amount, $sch = false)
{
	global $schema, $app;

	$sch = ($sch) ?: $schema;

	$data = [
		'from_id'	=> $from_id,
		'to_id'		=> $to_id,
		'amount'	=> $amount,
		'schema'	=> $sch,
	];

	$app['eland.queue']->set('autominlimit', $data);
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
