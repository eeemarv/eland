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

$app->register(new Silex\Provider\SessionServiceProvider(), [
	'session.storage.handler'	=> new eland\redis_session($app['redis']),
	'session.storage.options'	=> [
		'name'						=> 'eland',
		'cookie_domain'				=> '.' . getenv('OVERALL_DOMAIN'),
		'cookie_lifetime'			=> 172800,
	],
]);

$app->register(new Silex\Provider\TwigServiceProvider(), [
	'twig.path' => __DIR__ . '/../views',
	'twig.options'	=> [
		'cache'		=> __DIR__ . '/../cache',
		'debug'		=> getenv('DEBUG'),
	],
]);

$app->extend('twig', function($twig, $app) {

//	$twig->addFilter(new Twig_SimpleFilter('distance', array('eland\twig_extension', 'distance')));
//	$twig->addFilter(new Twig_SimpleFilter('geocode', array('eland\twig_extension', 'geocode')));
	$twig->addFilter(new Twig_SimpleFilter('date_format', array('eland\date_format', 'twig_get'), [
		'needs_environment'	=> true,
		'needs_context'		=> true,
	]));

	return $twig;
});

$app->register(new Silex\Provider\MonologServiceProvider(), []);

$app->extend('monolog', function($monolog, $app) {

	$monolog->setTimezone(new DateTimeZone('UTC'));

	$handler = new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG);
	$handler->setFormatter(new \Bramus\Monolog\Formatter\ColoredLineFormatter());
	$monolog->pushHandler($handler);

	$handler = new \Monolog\Handler\RedisHandler($app['redis'], 'monolog_logs', \Monolog\Logger::DEBUG, true, 20);
	$handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
	$monolog->pushHandler($handler);

	$monolog->pushProcessor(new Monolog\Processor\WebProcessor());

	$monolog->pushProcessor(function ($record) use ($app){

		$record['extra']['schema'] = $app['eland.this_group']->get_schema();

		if (isset($app['s_ary_user']))
		{
			$record['extra']['letscode'] = $app['s_ary_user']['letscode'] ?? '';
			$record['extra']['user_id'] = $app['s_ary_user']['id'] ?? '';
			$record['extra']['username'] = $app['s_ary_user']['name'] ?? '';
		}

		if (isset($app['s_schema']))
		{
			$record['extra']['user_schema'] = $app['s_schema'];
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

$app['eland.page_access'] = $page_access;

$app['eland.protocol'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";



$app['eland.s3_res'] = getenv('S3_RES') ?: die('Environment variable S3_RES S3 bucket for resources not defined.');
$app['eland.s3_img'] = getenv('S3_IMG') ?: die('Environment variable S3_IMG S3 bucket for images not defined.');
$app['eland.s3_doc'] = getenv('S3_DOC') ?: die('Environment variable S3_DOC S3 bucket for documents not defined.');

$header_allow_origin = $app['eland.protocol'] . $app['eland.s3_res'] . ', ';
$header_allow_origin .= $app['eland.protocol'] . $app['eland.s3_img'] . ', ';
$header_allow_origin .= $app['eland.protocol'] . $app['eland.s3_doc'];

if (isset($no_headers))
{
	ob_start('etag_buffer');
	header('Access-Control-Allow-Origin: ' . $header_allow_origin);
}

$app['eland.s3_res_url'] = $app['eland.protocol'] . $app['eland.s3_res'] . '/';
$app['eland.s3_img_url'] = $app['eland.protocol'] . $app['eland.s3_img'] . '/';
$app['eland.s3_doc_url'] = $app['eland.protocol'] . $app['eland.s3_doc'] . '/';

$app['eland.s3'] = function($app){
	return new eland\s3($app['eland.s3_res'], $app['eland.s3_img'], $app['eland.s3_doc']);
};

$app['eland.assets'] = function($app){
	return new eland\assets($app['eland.s3_res_url'], $app['eland.rootpath']);
};

$app['eland.assets']->add(['jquery', 'bootstrap', 'fontawesome', 'footable', 'base.css', 'print.css', 'base.js']);

$app['eland.script_name'] = str_replace('.php', '', ltrim($_SERVER['SCRIPT_NAME'], '/'));

$app['eland.base_url'] = $app['eland.protocol'] . $_SERVER['SERVER_NAME'];

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

$app['eland.log_db'] = function($app){
	return new eland\log_db($app['db'], $app['redis']);
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
//	'index'			=> true,
	'messages'		=> true,
	'users'			=> true,
	'transactions'	=> true,
	'news'			=> true,
	'docs'			=> true,
];

/*
 * check if we are on the request hosting url.
 */
$key_host_env = str_replace(['.', '-'], ['__', '___'], strtoupper($_SERVER['SERVER_NAME']));

if ($app['eland.script_name'] == 'index' && getenv('HOSTING_FORM_' . $key_host_env))
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
	header('Location: ' . $app['eland.protocol'] . $redirect . $_SERVER['REQUEST_URI']);
	exit;
}

/**
 * Get all eland schemas and domains
 */

$app['eland.groups'] = function ($app){
	return new eland\groups($app['db']);
};

$app['eland.this_group'] = function($app){
	return new eland\this_group($app['eland.groups'], $app['db'], $app['redis'], $app['twig']);
};

$app['eland.xdb'] = function ($app){
	return new eland\xdb($app['db'], $app['monolog'], $app['eland.this_group']);
};

$app['eland.alert'] = function ($app){
	return new eland\alert($app['monolog'], $app['session']);
};

$app['eland.pagination'] = function (){
	return new eland\pagination();
};

$app['eland.interlets_groups'] = function ($app){
	return new eland\interlets_groups($app['db'], $app['redis'], $app['eland.groups'], $app['eland.protocol']);
};

$app['eland.password_strength'] = function ($app){
	return new eland\password_strength();
};

$app['eland.user'] = function ($app){
	return new eland\user($app['eland.this_group'], $app['monolog'], $app['session'], $app['eland.page_access']);
};

/** user **/

$p_role = $_GET['r'] ?? 'anonymous';
$p_user = $_GET['u'] ?? false;
$p_schema = $_GET['s'] ?? false;

$s_schema = ($p_schema) ?: $app['eland.this_group']->get_schema();
$s_id = $p_user;
$s_accountrole = isset($access_ary[$p_role]) ? $p_role : 'anonymous';

$s_group_self = ($s_schema == $app['eland.this_group']->get_schema()) ? true : false;

/** access user **/

$logins = $app['session']->get('logins') ?? [];

$s_master = $s_elas_guest = false;

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
		$location = $app['eland.protocol'] . $app['eland.groups']->get_host($s_schema) . '/messages.php?r=';
		$location .= $session_user['accountrole'] . '&u=' . $s_id;
		header('Location: ' . $location);
		exit;
	}

	if ($access_ary[$session_user['accountrole']] > $access_ary[$s_accountrole])
	{
		$app['monolog']->debug('redirect 2');

		$s_accountrole = $session_user['accountrole'];

		redirect_default_page();
	}

	if (!($session_user['status'] == 1 || $session_user['status'] == 2))
	{
		$app['monolog']->debug('redirect 2a');

		$app['session']->invalidate();
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

		$location = $app['eland.protocol'] . $app['eland.groups']->get_host($s_schema) . '/messages.php?r=admin&u=master';
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
			redirect_default_page();
		}

		break;

	case 'user':

		if (!($page_access == 'user' || $page_access == 'guest'))
		{
			$app['monolog']->debug('redirect 7');
			redirect_default_page();
		}

		break;

	case 'admin':

		if ($page_access == 'anonymous')
		{
			$app['monolog']->debug('redirect 8');
			redirect_default_page();
		}

		break;

	default:

		$app['monolog']->debug('redirect 9');
		redirect_login();

		break;
}

$app['eland.access_control'] = function($app){
	return new eland\access_control($app['eland.this_group']);
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
	&& !$eland_interlets_groups[$app['eland.this_group']->get_schema()])
{
	header('Location: ' . generate_url('messages', ['view' => $view_messages], $s_schema));
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

$app['eland.xdb']->init($s_schema, $s_id);

$app['eland.queue'] = function ($app){
	return new eland\queue($app['db'], $app['monolog']);
};

$app['eland.date_format'] = function(){
	return new eland\date_format();
};

$app['eland.form_token'] = function ($app){
	return new eland\form_token($app['redis'], $app['monolog'], $app['eland.script_name']);
};

$app['eland.mailaddr'] = function ($app){
	return new eland\mailaddr($app['db'], $app['monolog'], $app['eland.this_group'], $app['eland.script_name']);
};

// tasks

$app['eland.task.mail'] = function ($app){
	return new eland\task\mail($app['eland.queue'], $app['monolog'],
		$app['eland.this_group'], $app['eland.mailaddr'], $app['twig']);
};

$app['eland.task.autominlimit'] = function ($app){
	return new eland\task\autominlimit($app['eland.queue'], $app['monolog'],
		$app['eland.xdb'], $app['db']);
};

$app['eland.task.geocode'] = function ($app){
	return new eland\task\geocode($app['redis'], $app['db'], $app['eland.xdb'],
		$app['eland.queue'], $app['monolog']);
};

$app['eland.task.cleanup_image_files'] = function ($app){
	return new eland\task\cleanup_image_files($app['redis'], $app['db'], $app['monolog'],
		$app['eland.s3'], $app['eland.groups']);
};

$app['eland.task.cleanup_messages'] = function ($app){
	return new eland\task\cleanup_messages($app['db'], $app['monolog']);
};

$app['eland.task.cleanup_news'] = function ($app){
	return new eland\task\cleanup_news($app['db'], $app['eland.xdb'], $app['monolog']);
};

$app['eland.task.cleanup_logs'] = function ($app){
	return new eland\task\cleanup_logs($app['db'], $app['eland.xdb']);
};

$app['eland.task.saldo_update'] = function ($app){
	return new eland\task\saldo_update($app['db'], $app['monolog']);
};

$app['eland.task.user_exp_msgs'] = function ($app){
	return new eland\task\user_exp_msgs($app['db'], $app['eland.task.mail'],
		$app['eland.groups'], $app['eland.protocol']);
};

$app['eland.task.saldo'] = function ($app){
	return new eland\task\saldo($app['db'], $app['eland.xdb'], $app['monolog'], $app['eland.task.mail'],
		$app['eland.groups'], $app['eland.s3_img_url'], $app['eland.protocol']);
};

$app['eland.task.interlets_fetch'] = function ($app){
	return new eland\task\interlets_fetch($app['redis'], $app['db'], $app['eland.xdb'],
		$app['eland.typeahead'], $app['monolog'], $app['eland.groups']);
};

$app['eland.cron_schedule'] = function ($app){
	return new eland\cron_schedule($app['db'], $app['monolog'], $app['eland.xdb'],
		$app['eland.groups'], $app['eland.this_group']);
};

//

$app['eland.elas_db_upgrade'] = function ($app){
	return new eland\elas_db_upgrade($app['db']);
};


/* view (global for all groups) */

$inline = isset($_GET['inline']) ? true : false;

$view = $_GET['view'] ?? false;

$view_users = $app['session']->get('view.users') ?? 'list';
$view_messages = $app['session']->get('view.messages') ?? 'extended';
$view_news = $app['session']->get('view.news') ?? 'extended';

if ($view || $inline)
{
	if ($app['eland.script_name'] == 'users' && $view != $view_users)
	{
		$view = $view_users = ($view) ?: $view_users;
		$app['session']->set('view.users', $view_users);
	}
	else if ($app['eland.script_name'] == 'messages' && $view != $view_messages)
	{
		$view = $view_messages = ($view) ?: $view_messages;
		$app['session']->set('view.messages', $view);
	}
	else if ($app['eland.script_name'] == 'news' && $view != $view_news)
	{
		$view = $view_news = ($view) ?: $view_news;
		$app['session']->set('view.news', $view);
	}
}

/**
 * remember adapted role in own group (for links to own group)
 */
if (!$s_anonymous)
{
	if ($s_master || $session_user['accountrole'] == 'admin' || $session_user['accountrole'] == 'user')
	{
		if (isset($logins[$app['eland.this_group']->get_schema()]) && $s_group_self)
		{
			$app['session']->set('role.' . $app['eland.this_group']->get_schema(), $s_accountrole);
		}

		$s_user_params_own_group = [
			'r' => $app['session']->get('role.' . $s_schema),
			'u'	=> $s_id,
		];
	}
	else
	{
		$s_user_params_own_group = [];
	}
}

/* some more vars */

$app['s_ary_user'] = $session_user ?? [];
$app['s_schema'] = $s_schema;

$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

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
function generate_url($entity = 'messages', $params = [], $sch = false)
{
	global $rootpath, $app;

	if ($app['eland.alert']->is_set())
	{
		$params['a'] = '1';
	}

	$params = array_merge($params, get_session_query_param($sch));

	$params = http_build_query($params);

	$params = ($params) ? '?' . $params : '';

	$path = ($sch) ? $app['eland.protocol'] . $app['eland.groups']->get_host($sch) . '/' : $rootpath;

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

function redirect_default_page()
{
	global $p_role, $p_user, $p_schema, $access_level, $access_session;
	global $s_id, $s_accountrole, $s_schema;

	$access_level = $access_session;

	$p_schema = $s_schema;
	$p_user = $s_id;
	$p_role = $s_accountrole;

	header('Location: ' . get_default_page());
	exit;
}

function get_default_page()
{
	global $view_messages, $view_users, $view_news;
	static $default_page;

	if (isset($default_page))
	{
		return $default_page;
	}

	$page = readconfigfromdb('default_landing_page');

	$param = [];

	switch ($page)
	{
		case 'messages':
		case 'users':
		case 'news':

			$view_param = 'view_' . $page;
			$param['view'] = $$view_param;

			break;

		default:

			break;
	}

	$default_page = generate_url($page, $param);

	return $default_page;
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
    global $app, $s_guest, $s_master;
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
		'weekly_mail_show_interlets'		=> 'recent',
		'weekly_mail_show_news'			=> 'recent',
		'weekly_mail_show_forum'			=> 'recent',
		'weekly_mail_show_transactions'	=> 'recent',
		'weekly_mail_show_leaving_users'	=> 'all',
		'weekly_mail_show_new_users'		=> 'all',
		'default_landing_page'				=> 'messages',
		'homepage_url'						=> '',
	];

    if (!isset($sch))
    {
		$sch = $app['eland.this_group']->get_schema();
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

		if (!$s_guest && !$s_master)
		{
			$app['eland.xdb']->set('setting', $key, ['value' => $value], $sch);
		}
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
    global $app;
    static $cache;

	if (!$id)
	{
		return [];
	}

	$s = ($remote_schema) ?: $app['eland.this_group']->get_schema();

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
