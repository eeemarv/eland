<?php

use util\cnst;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/default.php';

$app['page_access'] = $page_access;

header('Cache-Control: private, no-cache');
header('Access-Control-Allow-Origin: ' . rtrim($app['s3_url'], '/') . ', http://img.letsa.net');

$app['assets']->add([
	'jquery',
	'bootstrap',
	'fontawesome',
	'footable',
	'base.css',
	'base.js',
]);

$app['assets']->add_print_css(['print.css']);

$app['script_name'] = str_replace('.php', '', ltrim($_SERVER['SCRIPT_NAME'], '/'));
$app['server_name'] = $_SERVER['SERVER_NAME'];
$app['base_url'] = $app['protocol'] . $app['server_name'];
$app['request_uri'] = $_SERVER['REQUEST_URI'];
$app['is_http_post'] = $_SERVER['REQUEST_METHOD'] == 'GET' ? false : true;
$app['mapbox_token'] = getenv('MAPBOX_TOKEN');

/*
 * check if we are on the contact url.
 */
$app['env_server_name'] = str_replace('.', '__', strtoupper($app['server_name']));

if ($app['script_name'] == 'index'
	&& getenv('APP_HOSTER_CONTACT_' . $app['env_server_name']))
{
	$app['page_access'] = 'anonymous';
	$app['app_hoster_contact'] = getenv('APP_HOSTER_CONTACT_' . $app['env_server_name']);
	return;
}

/*
 * permanent redirects
 */

if ($app_redirect = getenv('APP_REDIRECT_' . $app['env_server_name']))
{
	header('HTTP/1.1 301 Moved Permanently');
	header('Location: ' . $app['protocol'] . $app_redirect . $app['request_uri']);
	exit;
}

$app['tschema'] = $app['groups']->get_schema($app['server_name']);

if (!$app['tschema'])
{
	http_response_code(404);
	echo $app['twig']->render('404.html.twig');
	exit;
}

if (getenv('WEBSITE_MAINTENANCE'))
{
	echo $app['twig']->render('website_maintenance.html.twig',
		['message' =>  getenv('WEBSITE_MAINTENANCE')]);
	exit;
}

if (isset($_GET['ev']))
{
	$app['email_validate']->validate($_GET['ev']);
}

/**
 * vars
 **/

$top_right = '';
$top_buttons = '';

/** user **/

$app['session_user'] = [];

$app['p_role'] = $_GET['r'] ?? 'anonymous';
$app['p_user'] = $_GET['u'] ?? false;
$app['p_schema'] = $_GET['s'] ?? false;

$app['s_schema'] = $app['p_schema'] ?: $app['tschema'];
$app['s_id'] = $app['p_user'];
$app['s_accountrole'] = isset(cnst::ACCESS_ARY[$app['p_role']])
	? $app['p_role']
	: 'anonymous';
$app['s_group_self'] = $app['s_schema'] === $app['tschema'];

/** access user **/

$app['s_logins'] = $app['session']->get('logins') ?? [];
$app['s_master'] = $app['s_elas_guest'] = false;

if (!count($app['s_logins']))
{
	if ($app['s_accountrole'] != 'anonymous')
	{
		redirect_login();
	}
}

if (!$app['s_id'])
{
	if ($app['page_access'] != 'anonymous')
	{
		if (isset($app['s_logins'][$app['s_schema']])
			&& ctype_digit((string) $app['s_logins'][$app['s_schema']]))
		{
			$app['s_id'] = $app['s_logins'][$app['s_schema']];

			$location = parse_url($app['request_uri'], PHP_URL_PATH);
			$get = $_GET;

			unset($get['u'], $get['s'], $get['r']);

			$app['session_user'] = $app['user_cache']->get($app['s_id'], $app['s_schema']);

			$get['r'] = $app['session_user']['accountrole'];
			$get['u'] = $app['s_id'];

			if (!$app['s_group_self'])
			{
				$get['s'] = $app['s_schema'];
			}

			$get = http_build_query($get);
			header('Location: ' . $location . '?' . $get);
			exit;
		}

		redirect_login();
	}

	if ($app['s_accountrole'] != 'anonymous')
	{
		redirect_login();
	}
}
else if (!isset($app['s_logins'][$app['s_schema']]))
{
	if ($app['s_accountrole'] != 'anonymous')
	{
		redirect_login();
	}
}
else if ($app['s_logins'][$app['s_schema']] != $app['s_id']
	|| !$app['s_id'])
{
	$app['s_id'] = $app['s_logins'][$app['s_schema']];

	if (ctype_digit((string) $app['s_id']))
	{
		$location = parse_url($app['request_uri'], PHP_URL_PATH);
		$get = $_GET;

		unset($get['u'], $get['s'], $get['r']);

		$app['session_user'] = $app['user_cache']->get($app['s_id'], $app['s_schema']);

		$get['r'] = $app['session_user']['accountrole'];
		$get['u'] = $app['s_id'];

		if (!$app['s_group_self'])
		{
			$get['s'] = $app['s_schema'];
		}

		$get = http_build_query($get);
		header('Location: ' . $location . '?' . $get);
		exit;
	}

	redirect_login();
}
else if (ctype_digit((string) $app['s_id']))
{
	$app['session_user'] = $app['user_cache']->get($app['s_id'], $app['s_schema']);

	if (!$app['s_group_self'] && $app['s_accountrole'] != 'guest')
	{
		$location = $app['protocol'] . $app['groups']->get_host($app['s_schema']) . '/messages.php?r=';
		$location .= $app['session_user']['accountrole'] . '&u=' . $app['s_id'];
		header('Location: ' . $location);
		exit;
	}

	if (cnst::ACCESS_ARY[$app['session_user']['accountrole']] > cnst::ACCESS_ARY[$app['s_accountrole']])
	{
		$app['s_accountrole'] = $app['session_user']['accountrole'];

		redirect_default_page();
	}

	if (!($app['session_user']['status'] == 1 || $app['session_user']['status'] == 2))
	{
		$app['session']->invalidate();
		redirect_login();
	}
}
else if ($app['s_id'] == 'elas')
{
	if ($app['s_accountrole'] != 'guest' || !$app['s_group_self'])
	{
		redirect_login();
	}

	$app['s_elas_guest'] = true;
}
else if ($app['s_id'] == 'master')
{
	if (!$app['s_group_self'] && $app['s_accountrole'] != 'guest')
	{
		$location = $app['protocol'] . $app['groups']->get_host($app['s_schema']) . '/messages.php?r=admin&u=master';
		header('Location: ' . $location);
		exit;
	}

	$app['s_master'] = true;
}
else
{
	redirect_login();
}

/** page access **/

if (!isset($app['page_access'])
	|| !in_array($app['page_access'], ['anonymous', 'guest', 'user', 'admin']))
{
	http_response_code(500);

	echo $app['twig']->render('500.html.twig');
	exit;
}

switch ($app['s_accountrole'])
{
	case 'anonymous':

		if ($app['page_access'] != 'anonymous')
		{
			redirect_login();
		}

		break;

	case 'guest':

		if ($app['page_access'] != 'guest')
		{
			redirect_default_page();
		}

		break;

	case 'user':

		if (!($app['page_access'] == 'user' || $app['page_access'] == 'guest'))
		{
			redirect_default_page();
		}

		break;

	case 'admin':

		if ($app['page_access'] == 'anonymous')
		{
			redirect_default_page();
		}

		break;

	default:

		redirect_login();

		break;
}


/**
 * some vars
 **/

$app['s_access_level'] = cnst::ACCESS_ARY[$app['s_accountrole']];

$app['s_admin'] = $app['s_accountrole'] === 'admin';
$app['s_user'] = $app['s_accountrole'] === 'user';
$app['s_guest'] = $app['s_accountrole'] === 'guest';
$app['s_anonymous'] = !($app['s_admin'] || $app['s_user'] || $app['s_guest']);

$errors = [];

/**
 * Load interSystems
 **/

if ($app['config']->get('template_lets', $app['tschema'])
	&& $app['config']->get('interlets_en', $app['tschema']))
{
	$app['intersystem_ary'] = [
		'elas'	=> $app['interlets_groups']->get_elas($app['s_schema']),
		'eland'	=> $app['interlets_groups']->get_eland($app['s_schema']),
	];
}
else
{
	$app['intersystem_ary'] = [
		'elas'	=> [],
		'eland'	=> [],
	];
}

if ($app['s_group_self'] && $app['s_guest'])
{
	$app['intersystem_ary'] = [
		'elas'	=> [],
		'eland'	=> [],
	];
}

$app['count_intersystems'] = count($app['intersystem_ary']['eland']) + count($app['intersystem_ary']['elas']);

if ($app['page_access'] != 'anonymous'
	&& !$app['s_group_self']
	&& !$app['intersystem_ary']['eland'][$app['tschema']])
{
	header('Location: ' .
		generate_url('messages',
			[],
			$app['s_schema']));
	exit;
}

if ($app['page_access'] != 'anonymous'
	&& !$app['s_admin']
	&& $app['config']->get('maintenance', $app['tschema']))
{
	echo $app['twig']->render('maintenance.html.twig');
	exit;
}

$app['xdb']->set_user($app['s_schema'],
	ctype_digit((string) $app['s_id']) ? $app['s_id'] : 0);

/* view (global for all systems) */

$app['p_inline'] = isset($_GET['inline']) ? true : false;
$app['p_view'] = $_GET['view'] ?? false;

$app['s_view'] = $app['session']->get('view') ?? [
	'users'		=> 'list',
	'messages'	=> 'extended',
	'news'		=> 'extended',
];

if ($app['script_name'] === 'users'
	&& $app['p_view'] !== $app['s_view']['users'])
{
	if ($app['p_view'])
	{
		$app['s_view'] = array_merge($app['s_view'], [
			'users'	=> $app['p_view'],
		]);

		$app['session']->set('view', $app['s_view']);
	}
	else
	{
		$app['p_view'] = $app['s_view']['users'];
	}
}
else if ($app['script_name'] === 'messages'
	&& $app['p_view'] !== $app['s_view']['messages'])
{
	if ($app['p_view'])
	{
		$app['s_view'] = array_merge($app['s_view'], [
			'messages'	=> $app['p_view'],
		]);

		$app['session']->set('view', $app['s_view']);
	}
	else
	{
		$app['p_view'] = $app['s_view']['messages'];
	}
}
else if ($app['script_name'] === 'news'
	&& $app['p_view'] !== $app['s_view']['news'])
{
	if ($app['p_view'])
	{
		$app['s_view'] = array_merge($app['s_view'], [
			'news'		=> $app['p_view'],
		]);

		$app['session']->set('view', $app['s_view']);
	}
	else
	{
		$app['p_view'] = $app['s_view']['news'];
	}
}

/**
 * remember adapted role in own group (for links to own group)
 */

if (!$app['s_anonymous'])
{
	if ($app['s_master']
		|| $app['session_user']['accountrole'] == 'admin'
		|| $app['session_user']['accountrole'] == 'user')
	{
		if (isset($app['s_logins'][$app['tschema']])
			&& $app['s_group_self'])
		{
			$app['session']->set('role.' . $app['tschema'],
				$app['s_accountrole']);
		}

		$app['s_user_params_own_system'] = [
			'r' => $app['session']->get('role.' . $app['s_schema']),
			'u'	=> $app['s_id'],
		];
	}
	else
	{
		$app['s_user_params_own_system'] = [];
	}
}

/* */

$app['new_user_treshold'] = time() - $app['config']->get('newuserdays', $app['tschema']) * 86400;

/** welcome message **/

if (isset($_GET['welcome']) && $app['s_guest'])
{
	$msg = '<strong>Welkom bij ';
	$msg .= $app['config']->get('systemname', $app['tschema']);
	$msg .= '</strong><br>';
	$msg .= 'Waardering bij ';
	$msg .= $app['config']->get('systemname', $app['tschema']);
	$msg .= ' gebeurt met \'';
	$msg .= $app['config']->get('currency', $app['tschema']);
	$msg .= '\'. ';
	$msg .= $app['config']->get('currencyratio', $app['tschema']);
	$msg .= ' ';
	$msg .= $app['config']->get('currency', $app['tschema']);
	$msg .= ' stemt overeen met 1 uur.<br>';

	if ($app['s_elas_guest'])
	{
		$msg .= 'Je bent ingelogd als gast, je kan informatie ';
		$msg .= 'raadplegen maar niets wijzigen. Transacties moet je ';
		$msg .= 'ingeven in je eigen Systeem.';
	}
	else
	{
		$msg .= 'Je kan steeds terug naar je eigen Systeem via het menu <strong>Systeem</strong> ';
		$msg .= 'boven in de navigatiebalk.';
	}

	$app['alert']->info($msg);
}

/**************** FUNCTIONS ***************/

function btn_item_nav(string $url, bool $next, bool $down):string
{
	$ret = ' class="btn btn-default" title="';
	$ret .= $next ? 'Volgende' : 'Vorige';
	$ret .= '"><i class="fa fa-chevron-';
	$ret .= $down ? 'down' : 'up';
	$ret .= '"></i>';

	if ($url)
	{
		return '<a href="' . $url . '"' . $ret . '</a>';
	}

	return '<button disabled="disabled"' . $ret . '</button>';
}

function btn_filter():string
{
	$ret = '<div class="pull-right">';
	$ret .= '&nbsp;<button class="btn btn-default hidden-xs" ';
	$ret .= 'title="Filters" ';
	$ret .= 'data-toggle="collapse" data-target="#filter"';
	$ret .= '><i class="fa fa-caret-down"></i>';
	$ret .= '<span class="hidden-xs hidden-sm"> ';
	$ret .= 'Filters</span></button>';
	$ret .= '</div>';
	return $ret;
}

/*
 * create link within eland with query parameters depending on user and role
 */

function aphp(
	string $entity,
	array $params = [],
	string $label = '*link*',
	$class = false,
	$title = false,
	$fa = false,
	$collapse = false,
	$attr = false,
	$sch = false):string
{
	$out = '<a href="';
	$out .= generate_url($entity, $params, $sch);
	$out .= '"';
	$out .= $class ? ' class="' . $class . '"' : '';
	$out .= $title ? ' title="' . $title . '"' : '';

	if (is_array($attr))
	{
		foreach ($attr as $name => $val)
		{
			$out .= ' ' . $name . '="' . $val . '"';
		}
	}
	$out .= '>';
	$out .= $fa ? '<i class="fa fa-' . $fa .'"></i>' : '';
	$out .= $collapse ? '<span class="hidden-xs hidden-sm"> ' : ' ';
	$out .= htmlspecialchars($label, ENT_QUOTES);
	$out .= $collapse ? '</span>' : '';
	$out .= '</a>';
	return $out;
}

/**
 * generate url
 */
function generate_url(string $entity, array $params = [], $sch = false):string
{
	global $app;

	$params = array_merge($params, get_session_query_param($sch));

	$params = http_build_query($params);

	$params = $params ? '?' . $params : '';

	$path = $sch ? $app['protocol'] . $app['groups']->get_host($sch) . '/' : $app['rootpath'];

	return $path . $entity . '.php' . $params;
}

/**
 * get session query param
 */
function get_session_query_param($sch = false):array
{
	global $app;
	static $ary;

	if ($sch)
	{
		if ($sch === $app['s_schema'])
		{
			return  $app['s_user_params_own_system'];
		}

		if ($app['s_schema'])
		{
			$param_ary = [
				'r' => 'guest',
				'u' => $app['s_id'],
				's' => $app['s_schema']
			];

			return $param_ary;
		}

		return ['r' => 'guest'];
	}

	if (isset($ary))
	{
		return $ary;
	}

	$ary = [];

	if ($app['p_role'] != 'anonymous')
	{
		$ary['r'] = $app['p_role'];
		$ary['u'] = $app['p_user'];

		if ($app['s_access_level'] === 2 && $app['p_schema'])
		{
			$ary['s'] = $app['p_schema'];
		}
	}

	return $ary;
}

function redirect_default_page()
{
	global $app;

	$app['p_schema'] = $app['s_schema'];
	$app['p_user'] = $app['s_id'];
	$app['p_role'] = $app['s_accountrole'];

	header('Location: ' . get_default_page());
	exit;
}

function get_default_page():string
{
	global $app;

	$page = $app['config']->get('default_landing_page', $app['tschema']);

	$default_page = generate_url($page, []);

	return $default_page;
}

/**
 *
 */
function redirect_login()
{
	global $app;
	$location = parse_url($app['request_uri'], PHP_URL_PATH);
	$get = $_GET;
	unset($get['u'], $get['s'], $get['r']);
	$query_string = http_build_query($get);
	$location .= ($query_string == '') ? '' : '?' . $query_string;
	header('Location: ' . $app['rootpath'] . 'login.php?location=' . urlencode($location));
	exit;
}

function get_select_options(array $option_ary, $selected):string
{
	$str = '';

	foreach ($option_ary as $key => $value)
	{
		$str .= '<option value="' . $key . '"';
		$str .= $key == $selected ? ' selected="selected"' : '';
		$str .= '>' . htmlspecialchars($value, ENT_QUOTES) . '</option>';
	}

	return $str;
}

function array_intersect_key_recursive(array $ary_1, array $ary_2)
{
	$ary_1 = array_intersect_key($ary_1, $ary_2);

    foreach ($ary_1 as $key => &$val)
    {
        if (is_array($val))
        {
            $val = is_array($ary_2[$key]) ? array_intersect_key_recursive($val, $ary_2[$key]) : $val;
        }
	}

    return $ary_1;
}

/** (dev) */
function print_r2($val)
{
	echo '<pre>';
	print_r($val);
	echo  '</pre>';
}