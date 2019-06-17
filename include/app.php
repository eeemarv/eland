<?php

use util\app;
use cnst\role as cnst_role;
use cnst\access as cnst_access;
use cnst\pages as cnst_pages;
use cnst\assert as cnst_assert;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

setlocale(LC_TIME, 'nl_NL.UTF-8');
date_default_timezone_set((getenv('TIMEZONE')) ?: 'Europe/Brussels');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/deps.php';

$fn_after_locale = function (Request $request, Response $response, app $app){
	$origin = rtrim($app['s3_url'], '/');
	$origin .= ', http://img.letsa.net';
	$response->headers->set('Access-Control-Allow-Origin', $origin);
};

$fn_before_locale = function (Request $request, app $app){

	$app['assets']->add([
		'jquery', 'bootstrap', 'fontawesome',
		'footable', 'base.css', 'base.js',
	]);

	$app['assets']->add_print_css(['print.css']);

	error_log('LOGINS: ' . json_encode($app['s_logins']));

//	$app['request'] = $request;
};

$fn_before_system = function(Request $request, app $app){

	if ($request->query->get('et') !== null)
	{
		$app['email_validate']->validate($request->query->get('et'));
	}

};

$fn_before_system_auth = function(Request $request, app $app){

};

$fn_before_system_guest = function(Request $request, app $app){

	if ($app['pp_role'] === 'guest' && !$app['intersystem_en'])
	{
		throw new NotFoundHttpException('Guest routes not enabled (intersystem_en)');
	}




};

$fn_before_system_role = function(Request $request, app $app){

};

$fn_before_system_user = function(Request $request, app $app){

};

$fn_before_system_admin = function(Request $request, app $app){

};

$app['controllers']
	->assert('id', cnst_assert::NUMBER)
	->assert('days', cnst_assert::NUMBER)
	->assert('locale', cnst_assert::LOCALE)
	->assert('role_short', cnst_assert::GUEST)
	->assert('system', cnst_assert::SYSTEM);

$c_locale = $app['controllers_factory'];
$c_system_anon = $app['controllers_factory'];
$c_system_guest = $app['controllers_factory'];
$c_system_user = $app['controllers_factory'];
$c_system_admin = $app['controllers_factory'];

$c_locale->assert('_locale', cnst_assert::LOCALE)
	->after($fn_after_locale)
	->before($fn_before_locale);

$c_system_anon->assert('_locale', cnst_assert::LOCALE)
	->assert('system', cnst_assert::SYSTEM)
	->after($fn_after_locale)
	->before($fn_before_locale)
	->before($fn_before_system);

$c_system_guest->assert('_locale', cnst_assert::LOCALE)
	->assert('system', cnst_assert::SYSTEM)
	->assert('role_short', cnst_assert::GUEST)
	->assert('id', cnst_assert::NUMBER)
	->assert('view', cnst_assert::VIEW)
	->after($fn_after_locale)
	->before($fn_before_locale)
	->before($fn_before_system)
	->before($fn_before_system_auth)
	->before($fn_before_system_guest);

$c_system_user->assert('_locale', cnst_assert::LOCALE)
	->assert('system', cnst_assert::SYSTEM)
	->assert('role_short', cnst_assert::USER)
	->assert('id', cnst_assert::NUMBER)
	->assert('view', cnst_assert::VIEW)
	->after($fn_after_locale)
	->before($fn_before_locale)
	->before($fn_before_system)
	->before($fn_before_system_auth)
	->before($fn_before_system_role)
	->before($fn_before_system_user);

$c_system_admin->assert('_locale', cnst_assert::LOCALE)
	->assert('system', cnst_assert::SYSTEM)
	->assert('role_short', cnst_assert::ADMIN)
	->assert('id', cnst_assert::NUMBER)
	->assert('view', cnst_assert::VIEW)
	->after($fn_after_locale)
	->before($fn_before_locale)
	->before($fn_before_system)
	->before($fn_before_system_auth)
	->before($fn_before_system_role)
	->before($fn_before_system_admin);

$app->get('/monitor', 'controller\\monitor::get')
	->bind('monitor');

$app->get('/test', function () use ($app){

	$test = '<html><head></head><body>';
	$test .= '<p>TEST</p>';
	$test .= '</body>';

	return new Response($test);
});

$c_locale->match('/contact', 'controller\\contact_host::form')
	->bind('contact_host');


$c_system_anon->match('/login-elas/{elas_token}', 'controller\\login_elas_token::get')
	->assert('elas_token', cnst_assert::ELAS_TOKEN)
	->bind('login_elas_token');

$c_system_anon->match('/login', 'controller\\login::form')
	->bind('login');

$c_system_anon->match('/contact', 'controller\\contact::form')
	->bind('contact');

$c_system_anon->get('/contact/{token}', 'controller\\contact_token::get')
	->assert('token', cnst_assert::TOKEN)
	->bind('contact_token');

$c_system_anon->match('/register', 'controller\\register::form')
	->bind('register');

$c_system_anon->get('/register/{token}', 'controller\\register_token::get')
	->assert('token', cnst_assert::TOKEN)
	->bind('register_token');

$c_system_anon->match('/password-reset', 'controller\\password_reset::form')
	->bind('password_reset');

$c_system_anon->match('/password-reset/{token}', 'controller\\password_reset_token::form')
	->assert('token', cnst_assert::TOKEN)
	->bind('password_reset_token');

$c_system_guest->get('/logout', 'controller\\logout::get')
	->bind('logout');

$c_system_admin->get('/status', 'controller\\status::get')
	->bind('status');

$c_system_admin->match('/categories/del/{id}', 'controller\\categories_del::match')
	->bind('categories_del');

$c_system_admin->match('/categories/edit/{id}', 'controller\\categories_edit::match')
	->bind('categories_edit');

$c_system_admin->match('/categories/add', 'controller\\categories_add::match')
	->bind('categories_add');

$c_system_admin->get('/categories', 'controller\\categories::get')
	->bind('categories');

$c_system_admin->match('/contact-types/edit/{id}', 'controller\\contact_types_edit::match')
	->bind('contact_types_edit');

$c_system_admin->match('/contact-types/del/{id}', 'controller\\contact_types_del::match')
	->bind('contact_types_del');

$c_system_admin->match('/contact-types/add', 'controller\\contact_types_add::match')
	->bind('contact_types_add');

$c_system_admin->get('/contact-types', 'controller\\contact_types::get')
	->bind('contact_types');

$c_system_admin->match('/contacts/edit/{id}', 'controller\\contacts_edit::match')
	->bind('contacts_edit');

$c_system_admin->match('/contacts/del/{id}', 'controller\\contacts_del::match')
	->bind('contacts_del');

$c_system_admin->match('/contacts/add', 'controller\\contacts_add::match')
	->bind('contacts_add');

$c_system_admin->get('/contacts', 'controller\\contacts::get')
	->bind('contacts');

$c_system_admin->match('/config/{tab}', 'controller\\config::match')
	->assert('tab', cnst_assert::CONFIG_TAB)
	->value('tab', 'system-name')
	->bind('config');

$c_system_admin->match('/intersystems/edit/{id}', 'controller\\intersystems_edit::edit')
	->bind('intersystems_edit');

$c_system_admin->match('/intersystems/del/{id}', 'controller\\intersystems_del::match')
	->bind('intersystems_del');

$c_system_admin->match('/intersystems/add', 'controller\\intersystems_edit::add')
	->bind('intersystems_add');

$c_system_admin->get('/intersystems/{id}', 'controller\\intersystems_show::get')
	->bind('intersystems_show');

$c_system_admin->get('/intersystems', 'controller\\intersystems::get')
	->bind('intersystems');

$c_system_admin->match('/apikeys/del/{id}', 'controller\\apikeys::del')
	->bind('apikeys_del');

$c_system_admin->match('/apikeys/add', 'controller\\apikeys::add')
	->bind('apikeys_add');

$c_system_admin->match('/apikeys', 'controller\\apikeys::list')
	->bind('apikeys');

$c_system_admin->get('/export', 'controller\\export::get')
	->bind('export');

$c_system_admin->match('/autominlimit', 'controller\\autominlimit::form')
	->bind('autominlimit');

$c_system_admin->match('/mass-transaction', 'controller\\mass_transaction::form')
	->bind('mass_transaction');

$c_system_admin->get('/logs', 'controller\\logs::get')
	->bind('logs');

$c_system_user->match('/support', 'controller\\support::form')
	->bind('support');

$c_system_anon->get('/', 'controller\\home_system::get')
	->bind('home_system');

$c_system_user->get('/messages/extend/{id}/{days}',
		'controller\\messages_extend::get')
	->assert('id', cnst_assert::NUMBER)
	->assert('days', cnst_assert::NUMBER)
	->bind('messages_extend');

$c_system_guest->match('/messages', 'controller\\messages::match')
	->bind('messages');

$c_system_guest->match('/users/{id}', 'controller\\users_show::get')
	->assert('id', cnst_assert::NUMBER)
	->bind('users');

$c_system_guest->match('/users', 'controller\\users::match')
	->bind('users');

$c_system_guest->match('/transactions/{id}', 'controller\\transactions_show::get')
	->assert('id', cnst_assert::NUMBER)
	->bind('transactions_show');

$c_system_guest->match('/transactions', 'controller\\transactions::match')
	->bind('transactions');

$c_system_guest->match('/news', 'controller\\news::match')
	->bind('news');

$c_system_admin->match('/docs/edit/{doc_id}', 'controller\\docs_edit::match')
	->assert('doc_id', cnst_assert::DOC_ID)
	->bind('docs_edit');

$c_system_admin->match('/docs/del/{doc_id}', 'controller\\docs_del::match')
	->assert('doc_id', cnst_assert::DOC_ID)
	->bind('docs_del');

$c_system_admin->match('/docs/add/{map_id}', 'controller\\docs_add::match')
	->assert('map_id', cnst_assert::DOC_MAP_ID)
	->value('map_id', '')
	->bind('docs_add');

$c_system_admin->match('/docs/map-edit/{map_id}', 'controller\\docs_map_edit::match')
	->assert('map_id', cnst_assert::DOC_MAP_ID)
	->bind('docs_map_edit');

$c_system_guest->get('/docs/map/{map_id}', 'controller\\docs_map::get')
	->assert('map_id', cnst_assert::DOC_MAP_ID)
	->bind('docs_map');

$c_system_guest->get('/docs', 'controller\\docs::get')
	->bind('docs');

$c_system_user->match('/forum/edit/{forum_id}', 'controller\\forum_edit::match')
	->assert('forum_id', cnst_assert::FORUM_ID)
	->bind('forum_edit');

$c_system_user->match('/forum/del/{forum_id}', 'controller\\forum_del::match')
	->assert('forum_id', cnst_assert::FORUM_ID)
	->bind('forum_del');

$c_system_guest->match('/forum/{topic_id}', 'controller\\forum_topic::match')
	->assert('topic_id', cnst_assert::FORUM_ID)
	->bind('forum_topic');

$c_system_user->match('/forum/add-topic', 'controller\\forum_add_topic::match')
	->bind('forum_add_topic');

$c_system_guest->get('/forum', 'controller\\forum::get')
	->bind('forum');

$c_system_user->get('/typeahead-account-codes', 'controller\\typeahead_account_codes::get')
	->bind('typeahead_account_codes');

$c_system_guest->get('/typeahead-accounts/{status}', 'controller\\typeahead_accounts::get')
	->assert('status', cnst_assert::PRIMARY_STATUS)
	->bind('typeahead_accounts');

$c_system_admin->get('/typeahead-doc-map-names', 'controller\\typeahead_doc_map_names::get')
	->bind('typeahead_doc_map_names');

$c_system_user->get('/typeahead-eland-intersystem-accounts/{remote_schema}',
		'controller\\typeahead_eland_intersystem_accounts::get')
	->assert('remote_schema', cnst_assert::SCHEMA)
	->bind('typeahead_eland_intersystem_accounts');

$c_system_user->get('/typeahead-elas-intersystem-accounts/{group_id}',
		'controller\\typeahead_elas_intersystem_accounts::get')
	->assert('group_id', cnst_assert::NUMBER)
	->bind('typeahead_elas_intersystem_accounts');

$c_system_admin->get('/typeahead-log-types', 'controller\\typeahead_log_types::get')
	->bind('typeahead_log_types');

$c_system_user->get('/typeahead-postcodes', 'controller\\typeahead_postcodes::get')
	->bind('typeahead_postcodes');

$c_system_admin->get('/typeahead-usernames', 'controller\\typeahead_usernames::get')
	->bind('typeahead_usernames');

$c_system_guest->get('/elas-group-login/{group_id}', 'controller\\elas_group_login::get')
	->assert('group_id', cnst_assert::NUMBER)
	->bind('elas_group_login');

$c_system_admin->get('/elas-soap-status/{group_id}', 'controller\\elas_soap_status::get')
	->assert('group_id', cnst_assert::NUMBER)
	->bind('elas_soap_status');

$c_system_guest->get('/plot-user-transactions/{user_id}/{days}', 'controller\\plot_user_transactions::get')
	->assert('user_id', cnst_assert::NUMBER)
	->assert('days', cnst_assert::NUMBER)
	->bind('plot_user_transactions');

$c_system_admin->get('/transactions-sum-in/{days}', 'controller\\transactions_sum::in')
	->assert('days', cnst_assert::NUMBER)
	->bind('transactions_sum_in');

$c_system_admin->get('/transactions-sum-out/{days}', 'controller\\transactions_sum::out')
	->assert('days', cnst_assert::NUMBER)
	->bind('transactions_sum_out');

$c_system_admin->get('/weighted-balances/{days}', 'controller\\weighted_balances::get')
	->assert('days', cnst_assert::NUMBER)
	->bind('weighted_balances');

$c_system_anon->mount('/{role_short}', $c_system_guest);
$c_system_anon->mount('/{role_short}', $c_system_user);
$c_system_anon->mount('/{role_short}', $c_system_admin);
$c_locale->mount('/{system}', $c_system_anon);
$app->mount('/{_locale}', $c_locale);

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
/*
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
*/
/**
 * inline
 */

//$app['p_inline'] = isset($_GET['inline']) ? true : false;

/**
 * view (global for all systems)
 */

/* */
/*

*/
/** welcome message **/
/*
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
*/

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