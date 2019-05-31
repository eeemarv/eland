<?php

use util\app;
use cnst\role as cnst_role;
use cnst\access as cnst_access;
use cnst\pages as cnst_pages;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/default.php';

$app->before(function(Request $request, app $app){

	$app['assets']->add([
		'jquery', 'bootstrap', 'fontawesome',
		'footable', 'base.css', 'base.js',
	]);

	$app['assets']->add_print_css(['print.css']);

	$app['s_anonymous'] = true;
	$app['s_guest'] = false;
	$app['s_user'] = false;
	$app['s_admin'] = false;
	$app['s_master'] = false;
	$app['s_elas_guest'] = false;
	$app['s_system_self'] = true;

	$app['request'] = $request;
});

$app->after(function (Request $request, Response $response, app $app){
	$origin = rtrim($app['s3_url'], '/');
	$origin .= ', http://img.letsa.net';
	$response->headers->set('Access-Control-Allow-Origin', $origin);
});

$app['controllers']
	->assert('id', '\d+')
	->assert('locale', 'nl')
	->assert('role_short', '[gua]')
	->assert('system', '[a-z][a-z0-9]*');

$c_locale = $app['controllers_factory'];
$c_system_anon = $app['controllers_factory'];
$c_system_guest = $app['controllers_factory'];
$c_system_user = $app['controllers_factory'];
$c_system_admin = $app['controllers_factory'];

$c_locale->assert('_locale', 'nl');

$c_system_anon->assert('_locale', 'nl')
	->assert('system', '[a-z][a-z0-9]*')
	->before(function(Request $request, app $app){

		if ($request->query->get('et') !== null)
		{
			$app['email_validate']->validate($request->query->get('et'));
		}

		$system = $request->attributes->get('system');
		$app['tschema'] = $app['systems']->get_schema($system);
		$app['pp_system'] = $system;
		$app['pp_ary'] = ['system' => $system];
	});

$c_system_guest->assert('_locale', 'nl')
	->assert('system', '[a-z][a-z0-9]*')
	->assert('role_short', '[gua]')
	->assert('id', '\d+')
	->assert('view', 'extended|list|map|tiles')
	->before(function(Request $request, app $app){

	});

$c_system_user->assert('_locale', 'nl')
	->assert('system', '[a-z][a-z0-9]*')
	->assert('role_short', '[ua]')
	->assert('id', '\d+')
	->assert('view', 'extended|list|map|tiles')
	->before(function(Request $request, app $app){

	});

$c_system_admin->assert('_locale', 'nl')
	->assert('system', '[a-z][a-z0-9]*')
	->assert('role_short', 'a')
	->assert('id', '\d+')
	->assert('view', 'extended|list|map|tiles')
	->before(function(Request $request, app $app){

	});

$app->get('/monitor', function(Request $request){
	return new Response('OK');
})->bind('monitor');

$c_locale->match('/contact', function(Request $request){
	return new Response('Contact');
})->bind('contact_host');

$c_system_anon->match('/login', function () use ($app) {
	return render_legacy($app, 'login');
})->bind('login');

$c_system_anon->match('/contact', function () use ($app) {
	return render_legacy($app, 'contact');
})->bind('contact');

$c_system_anon->match('/register', function () use ($app) {
	return render_legacy($app, 'register');
})->bind('register');

$c_system_anon->match('/password-reset', function () use ($app) {
	return render_legacy($app, 'pwreset');
})->bind('password_reset');

$c_system_guest->get('/logout', function () use ($app) {
	return render_legacy($app, 'logout');
})->bind('logout');

$c_system_admin->get('/status', function () use ($app){
	return render_legacy($app, 'status');
})->bind('status');

$c_system_admin->get('/categories', function () use ($app){
	return render_legacy($app, 'categories');
})->bind('categories');

$c_system_admin->get('/contact-types', function () use ($app){
	return render_legacy($app, 'type_contact');
})->bind('contact_types');

$c_system_guest->get('/contacts', function () use ($app){
	return render_legacy($app, 'contacts');
})->bind('contacts');

$c_system_admin->get('/config', function () use ($app){
	return render_legacy($app, 'config');
})->bind('config');

$c_system_admin->get('/intersystem', function () use ($app){
	return render_legacy($app, 'intersystem');
})->bind('intersystem');

$c_system_admin->get('/apikeys', function () use ($app){
	return render_legacy($app, 'apikeys');
})->bind('apikeys');

$c_system_admin->get('/export', function () use ($app){
	return render_legacy($app, 'export');
})->bind('export');

$c_system_admin->get('/autominlimit', function () use ($app){
	return render_legacy($app, 'autominlimit');
})->bind('autominlimit');

$c_system_admin->get('/mass-transaction', function () use ($app){
	return render_legacy($app, 'mass_transaction');
})->bind('mass_transaction');

$c_system_admin->get('/logs', function () use ($app){
	return render_legacy($app, 'logs');
})->bind('logs');

$c_system_user->get('/support', function () use ($app){
	return render_legacy($app, 'support');
})->bind('support');

$c_system_guest->get('/', function () use ($app){
	return render_legacy($app, 'index');
})->bind('home');

$c_system_guest->get('/messages', function () use ($app){
	return render_legacy($app, 'messages');
})->bind('messages');

$c_system_guest->get('/users', function () use ($app){
	return render_legacy($app, 'users');
})->bind('users');

$c_system_guest->get('/transactions', function () use ($app){
	return render_legacy($app, 'transactions');
})->bind('transactions');

$c_system_guest->get('/docs', function () use ($app){
	return render_legacy($app, 'docs');
})->bind('docs');

$c_system_guest->get('/forum', function () use ($app){
	return render_legacy($app, 'forum');
})->bind('forum');

$c_system_user->get('/typeahead/account-codes', function () use ($app){
	return render_legacy($app, 'typeahead/account_codes');
})->bind('typeahead_account_codes');

$c_system_guest->get('/typeahead/accounts', function () use ($app){
	return render_legacy($app, 'typeahead/accounts');
})->bind('typeahead_accounts');

$c_system_admin->get('/typeahead/doc-map-names', function () use ($app){
	return render_legacy($app, 'typeahead/doc_map_names');
})->bind('typeahead_doc_map_names');

$c_system_user->get('/typeahead/eland-intersystem-accounts', function () use ($app){
	return render_legacy($app, 'typeahead/eland_intersystem_accounts');
})->bind('typeahead_eland_intersystem_accounts');

$c_system_user->get('/typeahead/elas-intersystem-accounts', function () use ($app){
	return render_legacy($app, 'typeahead/elas_intersystem_accounts');
})->bind('typeahead_elas_intersystem_accounts');

$c_system_admin->get('/typeahead/log-types', function () use ($app){
	return render_legacy($app, 'typeahead/log_types');
})->bind('typeahead_log_types');

$c_system_user->get('/typeahead/postcodes', function () use ($app){
	return render_legacy($app, 'typeahead/postcodes');
})->bind('typeahead_postcodes');

$c_system_guest->get('/elas-group-login', function () use ($app){
	return render_legacy($app, 'ajax/elas_group_login');
})->bind('elas_group_login');

$c_system_admin->get('/elas-soap-status', function () use ($app){
	return render_legacy($app, 'ajax/elas_soap_status');
})->bind('elas_soap_status');

$c_system_guest->get('/plot-user-transactions', function () use ($app){
	return render_legacy($app, 'ajax/plot_user_transactions');
})->bind('plot_user_transactions');

$c_system_admin->get('/transactions-sum', function () use ($app){
	return render_legacy($app, 'ajax/transactions_sum');
})->bind('transactions_sum');

$c_system_admin->get('/weighted-balances', function () use ($app){
	return render_legacy($app, 'ajax/weighted_balances');
})->bind('weighted_balances');

$c_system_anon->mount('/{role_short}', $c_system_guest);
$c_system_anon->mount('/{role_short}', $c_system_user);
$c_system_anon->mount('/{role_short}', $c_system_admin);
$c_locale->mount('/{system}', $c_system_anon);
$app->mount('/{_locale}', $c_locale);

function render_legacy(
	app $app,
	string $name
):Response
{
	ob_start();
	require_once __DIR__ . '/../' . $name . '.php';
	return new Response(ob_get_clean());
}

/**
 * Routes end
 *
 */


/*
 * check if we are on the contact url.
 */
/*
$app['env_server_name'] = str_replace('.', '__', strtoupper($app['server_name']));

if ($app['script_name'] == 'index'
	&& getenv('APP_HOSTER_CONTACT_' . $app['env_server_name']))
{
	$app['page_access'] = 'anonymous';
	$app['app_hoster_contact'] = getenv('APP_HOSTER_CONTACT_' . $app['env_server_name']);
	return;
}

/*
if (getenv('WEBSITE_MAINTENANCE'))
{
	echo $app['twig']->render('website_maintenance.html.twig',
		['message' =>  getenv('WEBSITE_MAINTENANCE')]);
	exit;
}
*/

$app->run();


/**
 * Route and parameters
 */
/*
$app['matched_route'] = $app['request']->attributes->get('_route');

if (isset($app['pp_role_short'])
	&& isset(cnst_role::LONG[$app['pp_role_short']]))
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

$app['intersystem_en'] = $app['config']->get('template_lets', $app['tschema'])
	&& $app['config']->get('interlets_en', $app['tschema']);

$app['s_system_self'] = true;
$app['s_schema'] = $app['tschema'];
$app['s_elas_guest'] = false;
$app['s_guest'] = false;
$app['s_user'] = false;
$app['s_admin'] = false;
$app['s_anonymous'] = false;
$app['s_master'] = false;

if ($app['pp_role'] === 'guest')
{
	if ($app['request']->query->get('s') !== null
		&& $app['request']->query->get('s') !== $app['tschema'])
	{
		$app['pp_ary']['s'] = $app['request']->query->get('s');
		$app['s_schema'] = $app['pp_ary']['s'];
		$app['s_system_self'] = false;
	}
	else if ($app['request']->query->get('elas_guest') !== null)
	{
		$app['pp_ary']['elas_guest'] = $app['request']->query->get('elas_guest');
		$app['s_elas_guest'] = true;
	}
}

switch ($app['pp_role'])
{
	case 'guest':
		$app['s_guest'] = true;
		break;

	case 'user':
		$app['s_user'] = true;
		break;

	case 'admin':
		$app['s_admin'] = true;
		break;

	case 'anonymous':
		$app['s_anonymous'] = true;
		break;

	default:
		internal_server_error($app['twig']);
		break;
}

*/
/**
 * load interSystems
 **/

/*
if ($app['intersystem_en'])
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
 * Authentication User: s_schema, s_id, session_user
 */

/*
$app['session_user'] = [];
$app['s_role'] = 'anonymous';
$app['s_id'] = 0;
$app['s_logins'] = $app['session']->get('logins') ?? [];
$app['s_auth_en'] = count($app['s_logins'])
	&& isset($app['s_logins'][$app['s_schema']]);

if ($app['s_auth_en'])
{
	switch($app['s_logins'][$app['s_schema']])
	{
		case 'master':
			$app['s_master'] = true;
			$app['s_role'] = 'admin';
			break;

		case 'elas':
			$app['s_role'] = 'guest';
			break;

		default:
			$app['s_id'] = $app['s_logins'][$app['s_schema']];
			$app['session_user'] = $app['user_cache']->get($app['s_id'], $app['s_schema']);
			$app['s_role'] = $app['session_user']['accountrole'];
			break;
	}
}

error_log($app['request']->getPathInfo());

/**
 * Authorization
 */
/*
if (!ctype_digit((string) $app['s_id']))
{
	unset($app['s_logins'][$app['s_schema']]);
	$app['session']->set('logins', $app['s_logins']);

	$app['monolog']->debug('Non numeric s_id: ' . $app['s_id'],
		['schema' => $app['tschema']]);

	$app['link']->redirect('login', ['system' => $app['pp_system']], []);
}

if (!$app['s_anonymous'] && $app['s_role'] === 'anonymous')
{
	$app['monolog']->debug('Not authenticated, redirect to login.',
		['schema' => $app['tschema']]);

	$app['link']->redirect('login', ['system' => $app['pp_system']], []);
}

if ($app['s_guest'] && !$app['intersystem_en'])
{
	$app['monolog']->debug('Guest routes disabled',
		['schema' => $app['tschema']]);


	if ($app['s_role'] === 'user')
	{
		if ($app['page_access'] === 'admin')
		{
			$app['link']->redirect($app['matched_route'], [
				'system' 		=> $app['pp_system'],
				'role_short' 	=> 'u',
			], []);
		}

		$landing_route = $app['config']->get('default_landing_page', $app['tschema']);

		$app['link']->redirect($landing_route, [
			'system' => $app['pp_system'],
			'role_short' => 'u',
		], []);
	}
	else if ($app['s_role'] === 'admin')
	{
		$app['link']->redirect($app['matched_route'],
			['system' => $app['pp_system'], 'role_short' => 'a'], []);
	}

	// s_role === 'guest'

	$app['link']->redirect('login', ['system' => $app['pp_system']], []);
}








if ($app['page_access'] === 'anonymous')
{
	if (!isset($app['allow_authenticated'])
		&& $app['s_auth_en'])
	{
		$default_landing_page = $app['config']->get('default_landing_page', $app['tschema']);
		$app['link']->redirect();
	}
}
else
{
	if (!$app['s_auth_en'])
	{
		$location = $app['request']->getQueryString();
		$location = isset($location) ? '?' . $location : '';
		$location = $app['request']->getPathInfo() . $location;

		$app['link']->redirect('login',
			['system' => $app['pp_system']],
			['location'	=> $location],
		);
	}
}

if ($app['s_guest'] && !$app['s_system_self'])
{
	if (!isset($app['intersystem_ary']['eland'][$app['tschema']]))
	{
		$app['link']->redirect('login',
			['system' => $app['pp_system']],
			[],
		);
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
		$location = $app['link']->redirect('messages', [
			'system' => $app['systems']->get_system($app['s_schema']),
			'role_short' => cnst_role::SHORT[$app['session_user']['accountrole']],
		], []);
	}

	if (cnst::ACCESS_ARY[$app['session_user']['accountrole']] > cnst::ACCESS_ARY[$app['s_accountrole']])
	{
		$app['s_accountrole'] = $app['session_user']['accountrole'];

		$route = $app['config']->get('default_landing_page', $app['tschema']);

		$app['link']->redirect($route, $app['pp_ary'], []);

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
		$app['link']->redirect('messages', [
			'system' 		=> $app['systems']->get_system($app['s_schema']),
			'role_short' 	=> 'a',
		], []);
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
			$route = $app['config']->get('default_landing_page', $app['tschema']);

			$app['link']->redirect($route, $app['pp_ary'], []);
		}

		break;

	case 'user':

		if (!($app['page_access'] == 'user' || $app['page_access'] == 'guest'))
		{
			$route = $app['config']->get('default_landing_page', $app['tschema']);

			$app['link']->redirect($route, $app['pp_ary'], []);
		}

		break;

	case 'admin':

		if ($app['page_access'] == 'anonymous')
		{
			$page = $app['config']->get('default_landing_page', $app['tschema']);

			$app['link']->redirect($route, $app['pp_ary'], []);
		}

		break;

	default:

		redirect_login();

		break;
}

*/
/**
 * some vars
 **/

// $app['s_access_level'] = cnst::ACCESS_ARY[$app['p_role']];


$errors = [];

if ($app['page_access'] != 'anonymous'
	&& !$app['s_system_self']
	&& !$app['intersystem_ary']['eland'][$app['tschema']])
{
	$app['link']->redirect('messages', [
			'system'		=> $app['systems']->get_system($app['tschema']),
			'role_short'	=> $app['pp_role_short'],
		], []);
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

/**
 * inline
 */

$app['p_inline'] = isset($_GET['inline']) ? true : false;

/**
 * view (global for all systems)
 */

if (isset(cnst_pages::DEFAULT_VIEW[$app['matched_route']]))
{
	$app['s_view'] = $app['session']->get('view') ?? cnst_pages::DEFAULT_VIEW;

	if (isset($_GET['view'])
		&& $_GET['view'] !== $app['s_view'][$app['matched_route']])
	{
		$app['s_view'] = array_merge($app['s_view'], [
			$app['matched_route']	=> $_GET['view'],
		]);

		$app['session']->set('view', $app['s_view']);
	}

	$app['p_view'] = $app['s_view'][$app['matched_route']];
}

/* */

$app['new_user_treshold'] = time() - $app['config']->get('newuserdays', $app['tschema']) * 86400;

/** welcome message **/

if ($app['request']->query->get('welcome') !== null && $app['s_guest'])
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