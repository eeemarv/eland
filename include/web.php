<?php

use cnst\role as cnst_role;
use cnst\access as cnst_access;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/default.php';

header('Cache-Control: private, no-cache');
header('Access-Control-Allow-Origin: ' . rtrim($app['s3_url'], '/') .
	', http://img.letsa.net');

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

if (getenv('WEBSITE_MAINTENANCE'))
{
	echo $app['twig']->render('website_maintenance.html.twig',
		['message' =>  getenv('WEBSITE_MAINTENANCE')]);
	exit;
}

/** page access **/

if (!isset($app['page_access'])
	|| !isset(cnst_access::ACCESS[$app['page_access']]))
{
	internal_server_error($app['twig']);
}

if (isset($_GET['et']))
{
	$app['email_validate']->validate($_GET['et']);
}

/**
 *
 **/

$top_right = '';

/**
 *
 */

if (isset($app['pp_system']))
{
	$app['tschema'] = $app['systems']->get_schema_from_system($app['pp_system']);
}

if (!$app['tschema'])
{
	$app['tschema'] = $app['systems']->get_schema($app['server_name']);
}

if (!$app['tschema'])
{
	page_not_found($app['twig']);
}

if (isset($app['pp_role_short']) && isset(cnst_role::LONG[$app['pp_role_short']]))
{
	$app['pp_ary'] = [
		'system'		=> $app['pp_system'],
		'role_short'	=> $app['pp_role_short'],
	];
	$app['pp_role'] = cnst_role::LONG[$app['pp_role_short']];
}
else
{
	$app['pp_ary'] = [
		'system'	=> $app['pp_system'],
	];
	$app['pp_role'] = 'anonymous';
}

$app['matched_route'] = $app['request']->attributes->get('_route');

/**
 * authentication
 */

$app['session_user'] = [];

$app['s_schema'] = function () use ($app){

	$s_schema = $app['session']->get('schema');

	if (!isset($s_schema))
	{
		$s_schema = $app['tschema'];
		$app['session']->set('schema', $s_schema);
	}

	return $s_schema;
};

$app['s_system_self'] = $app['s_schema'] === $app['tschema'];

$app['s_logins'] = $app['session']->get('logins') ?? [];

$app['s_master'] = false;
$app['s_admin'] = false;
$app['s_user'] = false;
$app['s_guest'] = false;
$app['s_anonymous'] = false;
$app['s_elas_guest'] = false;
$app['s_id'] = 0;
$app['s_accountrole'] = 'anonymous';

if (count($app['s_logins']) && isset($app['s_logins'][$app['s_schema']]))
{
	switch ($app['s_logins']['s_schema'])
	{
		case 'master':
			$app['s_accountrole'] = 'admin';
			$app['s_master'] = true;
			break;
		case 'elas':
			$app['s_accountrole'] = 'guest';
			$app['s_guest'] = true;
			$app['s_elas_guest'] = true;
			break;
		default:
			$app['s_id'] = $app['s_logins'][$app['s_schema']];
			$app['session_user'] = $app['user_cache']->get($app['s_id'], $app['s_schema']);
			$app['s_accountrole'] = $app['session_user']['accountrole'];

			switch ($app['s_accountrole'])
			{
				case 'user':
					$app['s_admin'] = true;
					break;
				case 'user':
					$app['s_user'] = true;
					break;
				case 'guest':
					$app['s_guest'] = true;
					break;
				default:
					$s_logins = $app['s_logins'];
					unset($s_logins[$app['s_schema']]);
					$app['session']->set('logins', $s_logins);
					error_log('Unvalid accountrole ' .
						$app['s_accountrole'] .
						' for schema ' . $app['s_schema']);
					internal_server_error($app['twig']);
					break;
			}

			break;
	}
}

/**
 * load interSystems
 **/

if ($app['config']->get('template_lets', $app['tschema'])
	&& $app['config']->get('interlets_en', $app['tschema']))
{
	$app['intersystem_ary'] = [
		'elas'	=> $app['intersystems']->get_elas($app['s_schema']),
		'eland'	=> $app['intersystems']->get_eland($app['s_schema']),
	];
}
else
{
	$app['intersystem_ary'] = [
		'elas'	=> [],
		'eland'	=> [],
	];
}

if ($app['s_system_self'] && $app['s_guest'])
{
	$app['intersystem_ary'] = [
		'elas'	=> [],
		'eland'	=> [],
	];
}

$app['count_intersystems'] = count($app['intersystem_ary']['eland'])
	+ count($app['intersystem_ary']['elas']);

/**
 * authorization
 */

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

			if (!$app['s_system_self'])
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

		if (!$app['s_system_self'])
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

	if (!$app['s_system_self'] && $app['s_accountrole'] != 'guest')
	{
		$location = $app['protocol'] . $app['systems']->get_host($app['s_schema']) . '/messages.php?r=';
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
	if ($app['s_accountrole'] != 'guest' || !$app['s_system_self'])
	{
		redirect_login();
	}

	$app['s_elas_guest'] = true;
}
else if ($app['s_id'] == 'master')
{
	if (!$app['s_system_self'] && $app['s_accountrole'] != 'guest')
	{
		$location = $app['protocol'] . $app['systems']->get_host($app['s_schema']) . '/messages.php?r=admin&u=master';
		header('Location: ' . $location);
		exit;
	}

	$app['s_master'] = true;
}
else
{
	redirect_login();
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

// $app['s_access_level'] = cnst::ACCESS_ARY[$app['p_role']];

$errors = [];

if ($app['page_access'] != 'anonymous'
	&& !$app['s_system_self']
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
 * remember adapted role in own system (for links to own system)
 */

$app['s_ary'] = [];

if ($app['s_user'] || $app['s_admin'] || $app['s_master'])
{
	$app['session']->set('role_short.' . $app['tschema'], $app['pp_role_short']);
	$app['s_ary'] = $app['pp_ary'];
}
else if ($app['s_guest'] && !$app['s_elas_guest'])
{
	$app['s_ary'] = [
		'system'		=> $app['systems']->get_system_from_schema($app['s_schema']),
		'role_short'	=> $app['session']->get('role_short.' . $app['s_schema']),
	];
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

$app['render_stat']->before('nav', '')
	->after('nav', '');

/**************** FUNCTIONS ***************/

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

/**
 * generate url
 */
function generate_url(string $entity, array $params = [], string $schema):string
{
	global $app;

	$params = http_build_query($params);

	$params = $params ? '?' . $params : '';

	$path = $schema ? $app['protocol'] . $app['systems']->get_host($schema) . '/' : $app['rootpath'];

	return $path . $entity . $params;
}

function redirect_default_page()
{
	header('Location: ' . get_default_page());
	exit;
}

function get_default_page():string
{
	global $app;

	$page = $app['config']->get('default_landing_page', $app['tschema']);

	$default_page = generate_url($page, [], $app['tschema']);

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

function internal_server_error(Twig_Environment $twig)
{
	http_response_code(500);
	echo $twig->render('500.html.twig', []);
	exit;
}

function page_not_found(Twig_Environment $twig)
{
	http_response_code(404);
	echo $twig->render('404.html.twig');
	exit;
}

/** (dev) */
function print_r2($val)
{
	echo '<pre>';
	print_r($val);
	echo  '</pre>';
}