<?php declare(strict_types=1);

use util\app;
use cnst\assert as cnst_assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/deps.php';

Request::setTrustedProxies(
	['172.17.0.0/255'],
    Request::HEADER_X_FORWARDED_ALL
);

$fn_after_locale = function (Request $request, Response $response, app $app){
	$origin = rtrim($app['s3_url'], '/');
	$origin .= ', http://doc.letsa.net';
	$response->headers->set('Access-Control-Allow-Origin', $origin);
};

$fn_before_locale = function (Request $request, app $app){

	setlocale(LC_TIME, 'nl_NL.UTF-8');
	date_default_timezone_set((getenv('TIMEZONE')) ?: 'Europe/Brussels');

	$app['assets']->add([
		'jquery', 'bootstrap', 'fontawesome',
		'footable', 'base.css', 'base.js',
	]);

	$app['assets']->add_print_css(['print.css']);

	error_log('LOGINS: ' . json_encode($app['s_logins']));
};

$fn_before_system = function(Request $request, app $app){

	if ($app['pp_schema'] === '')
	{
		throw new NotFoundHttpException('Systeem ' . $app['pp_system'] . ' niet gevonden.');
	}

	if ($request->query->get('et') !== null)
	{
		$app['email_validate']->validate($request->query->get('et'));
	}

	if ($css = $app['config']->get('css', $app['pp_schema']))
	{
		$app['assets']->add_external_css([$css]);
	}

	$app['log_schema_en'] = true;
};

$fn_before_system_auth = function(Request $request, app $app){

	if (!isset($app['s_logins'][$app['s_schema']]))
	{
		$location = $request->getRequestUri();
		$app['link']->redirect('login', ['system' => $app['pp_system']],
			['location' => $location]);
	}
};

$fn_before_system_guest = function(Request $request, app $app){
	if ($app['pp_guest'])
	{
		if (!$app['intersystem_en'])
		{
			throw new NotFoundHttpException('Guest routes are not enabled in this system.');
		}

		if (!$app['s_system_self'])
		{
			$eland_intersystems = $app['intersystems']->get_eland($app['s_schema']);

			if (!isset($eland_intersystems[$app['pp_schema']]))
			{
				throw new AccessDeniedHttpException('System ' . $app['pp_system'] . ' permits no guest access to system ' . $app['pp_org_system']);
			}

			if (!in_array($app['s_role'], ['user', 'admin']))
			{
				throw new AccessDeniedHttpException('No permission for guest acces.');
			}
		}

		if ($request->query->get('welcome', '')
			&& (!$app['s_system_self'] || $app['s_elas_guest']))
		{
			$app['alert']->info($app['welcome_msg']);
		}
	}
};

$fn_before_system_user = function(Request $request, app $app){

	if ($app['pp_user'])
	{
		if (!$app['s_system_self'])
		{
			throw new AccessDeniedHttpException('You are not logged in this system.');
		}

		if (!in_array($app['s_role'], ['admin', 'user']))
		{
			throw new AccessDeniedHttpException('You have no access to the user pages.');
		}
	}
};

$fn_before_system_admin = function(Request $request, app $app){

	if ($app['pp_admin'])
	{
		if (!$app['s_system_self'])
		{
			throw new AccessDeniedHttpException('You are not logged in this system.');
		}

		if ($app['s_role'] !== 'admin')
		{
			throw new AccessDeniedHttpException('You have no access to the admin pages.');
		}
	}
};

$fn_before_system_init = function(Request $request, app $app){

	if (!getenv('APP_INIT_ENABLED'))
	{
		throw new NotFoundHttpException('Page not found');
	}
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
$c_system_init = $app['controllers_factory'];

$c_locale->assert('_locale', cnst_assert::LOCALE)
	->after($fn_after_locale)
	->before($fn_before_locale);

$c_system_anon->assert('_locale', cnst_assert::LOCALE)
	->assert('system', cnst_assert::SYSTEM)
	->assert('token', cnst_assert::TOKEN)
	->after($fn_after_locale)
	->before($fn_before_locale)
	->before($fn_before_system);

$c_system_guest->assert('_locale', cnst_assert::LOCALE)
	->assert('system', cnst_assert::SYSTEM)
	->assert('role_short', cnst_assert::GUEST)
	->assert('id', cnst_assert::NUMBER)
	->assert('user_id', cnst_assert::NUMBER)
	->assert('contact_id', cnst_assert::NUMBER)
	->assert('days', cnst_assert::NUMBER)
	->assert('view', cnst_assert::VIEW)
	->after($fn_after_locale)
	->before($fn_before_locale)
	->before($fn_before_system)
	->before($fn_before_system_auth)
	->before($fn_before_system_guest)
	->before($fn_before_system_user)
	->before($fn_before_system_admin);

$c_system_user->assert('_locale', cnst_assert::LOCALE)
	->assert('system', cnst_assert::SYSTEM)
	->assert('role_short', cnst_assert::USER)
	->assert('id', cnst_assert::NUMBER)
	->assert('user_id', cnst_assert::NUMBER)
	->assert('contact_id', cnst_assert::NUMBER)
	->assert('days', cnst_assert::NUMBER)
	->assert('view', cnst_assert::VIEW)
	->after($fn_after_locale)
	->before($fn_before_locale)
	->before($fn_before_system)
	->before($fn_before_system_auth)
	->before($fn_before_system_user)
	->before($fn_before_system_admin);

$c_system_admin->assert('_locale', cnst_assert::LOCALE)
	->assert('system', cnst_assert::SYSTEM)
	->assert('role_short', cnst_assert::ADMIN)
	->assert('id', cnst_assert::NUMBER)
	->assert('user_id', cnst_assert::NUMBER)
	->assert('contact_id', cnst_assert::NUMBER)
	->assert('days', cnst_assert::NUMBER)
	->assert('view', cnst_assert::VIEW)
	->after($fn_after_locale)
	->before($fn_before_locale)
	->before($fn_before_system)
	->before($fn_before_system_auth)
	->before($fn_before_system_admin);

$c_system_init->assert('_locale', cnst_assert::LOCALE)
	->assert('system', cnst_assert::SYSTEM)
	->assert('start', cnst_assert::NUMBER)
	->after($fn_after_locale)
	->before($fn_before_locale)
	->before($fn_before_system)
	->before($fn_before_system_init);

$app->get('/monitor', 'controller\\monitor::monitor')
	->bind('monitor');

$c_locale->match('/contact', 'controller\\index_contact::index_contact')
	->bind('index_contact');

$c_system_anon->match('/login-elas/{elas_token}',
		'controller\\login_elas_token::login_elas_token')
	->assert('elas_token', cnst_assert::ELAS_TOKEN)
	->bind('login_elas_token');

$c_system_anon->match('/login',
		'controller\\login::login')
	->bind('login');

$c_system_anon->match('/contact',
		'controller\\contact::contact')
	->bind('contact');

$c_system_anon->get('/contact/{token}',
		'controller\\contact_token::contact_token')
	->bind('contact_token');

$c_system_anon->match('/register',
		'controller\\register::register')
	->bind('register');

$c_system_anon->get('/register/{token}',
		'controller\\register_token::register_token')
	->bind('register_token');

$c_system_anon->match('/password-reset',
		'controller\\password_reset::password_reset')
	->bind('password_reset');

$c_system_anon->match('/password-reset/{token}',
		'controller\\password_reset_token::password_reset_token')
	->bind('password_reset_token');

$c_system_guest->get('/logout',
		'controller\\logout::logout')
	->bind('logout');

$c_system_admin->get('/status',
		'controller\\status::status')
	->bind('status');

$c_system_admin->match('/categories/{id}/del',
		'controller\\categories_del::categories_del')
	->bind('categories_del');

$c_system_admin->match('/categories/{id}/edit',
		'controller\\categories_edit::categories_edit')
	->bind('categories_edit');

$c_system_admin->match('/categories/add',
		'controller\\categories_add::categories_add')
	->bind('categories_add');

$c_system_admin->get('/categories',
		'controller\\categories::categories')
	->bind('categories');

$c_system_admin->match('/contact-types/{id}/edit',
		'controller\\contact_types_edit::contact_types_edit')
	->bind('contact_types_edit');

$c_system_admin->match('/contact-types/{id}/del',
		'controller\\contact_types_del::contact_types_del')
	->bind('contact_types_del');

$c_system_admin->match('/contact-types/add',
		'controller\\contact_types_add::contact_types_add')
	->bind('contact_types_add');

$c_system_admin->get('/contact-types',
		'controller\\contact_types::contact_types')
	->bind('contact_types');

$c_system_admin->match('/contacts/{id}/edit',
		'controller\\contacts_edit::contacts_edit_admin')
	->bind('contacts_edit_admin');

$c_system_admin->match('/contacts/{id}/del',
		'controller\\contacts_del::contacts_del_admin')
	->bind('contacts_del_admin');

$c_system_admin->match('/contacts/add',
		'controller\\contacts_add::contacts_add_admin')
	->bind('contacts_add_admin');

$c_system_admin->get('/contacts',
		'controller\\contacts::contacts')
	->bind('contacts');

$c_system_admin->match('/users/{user_id}/contacts/{contact_id}/edit',
		'controller\\users_contacts_edit::users_contacts_edit_admin')
	->bind('users_contacts_edit_admin');

$c_system_user->match('/users/contacts/{contact_id}/edit',
		'controller\\users_contacts_edit::users_contacts_edit')
	->bind('users_contacts_edit');

$c_system_admin->match('/users/{user_id}/contacts/{contact_id}/del',
		'controller\\users_contacts_del::users_contacts_del_admin')
	->bind('users_contacts_del_admin');

$c_system_user->match('/users/contacts/{contact_id}/del',
		'controller\\users_contacts_del::users_contacts_del')
	->bind('users_contacts_del');

$c_system_user->match('/users/{user_id}/contacts/add',
		'controller\\users_contacts_add::users_contacts_add_admin')
	->bind('users_contacts_add_admin');

$c_system_user->match('/users/contacts/add',
	'controller\\users_contacts_add::users_contacts_add')
	->bind('users_contacts_add');

$c_system_admin->match('/config/{tab}',
		'controller\\config::config')
	->assert('tab', cnst_assert::CONFIG_TAB)
	->value('tab', 'system-name')
	->bind('config');

$c_system_admin->match('/intersystems/{id}/edit',
		'controller\\intersystems_edit::intersystems_edit')
	->bind('intersystems_edit');

$c_system_admin->match('/intersystems/{id}/del',
		'controller\\intersystems_del::intersystems_del')
	->bind('intersystems_del');

$c_system_admin->match('/intersystems/add',
		'controller\\intersystems_edit::intersystems_add')
	->bind('intersystems_add');

$c_system_admin->get('/intersystems/{id}',
		'controller\\intersystems_show::intersystems_show')
	->bind('intersystems_show');

$c_system_admin->get('/intersystems',
		'controller\\intersystems::intersystems')
	->bind('intersystems');

$c_system_admin->match('/apikeys/{id}/del',
		'controller\\apikeys::apikeys_del')
	->bind('apikeys_del');

$c_system_admin->match('/apikeys/add',
		'controller\\apikeys::apikeys_add')
	->bind('apikeys_add');

$c_system_admin->match('/apikeys',
		'controller\\apikeys::apikeys')
	->bind('apikeys');

$c_system_admin->get('/export',
		'controller\\export::export')
	->bind('export');

$c_system_admin->match('/autominlimit',
		'controller\\autominlimit::autominlimit')
	->bind('autominlimit');

$c_system_admin->match('/mass-transaction',
		'controller\\mass_transaction::mass_transaction')
	->bind('mass_transaction');

$c_system_admin->get('/logs',
		'controller\\logs::logs')
	->bind('logs');

$c_system_user->match('/support',
		'controller\\support::support')
	->bind('support');

$c_system_user->post('/messages/{id}/images/{img}/del/{form_token}',
	'controller\\messages_images_del::messages_images_instant_del')
	->assert('img', cnst_assert::MESSAGE_IMAGE)
	->assert('form_token', cnst_assert::TOKEN)
	->bind('messages_images_instant_del');

$c_system_user->match('/messages/{id}/images/del',
	'controller\\messages_images_del::messages_images_del')
	->bind('messages_images_del');

$c_system_user->post('/messages/{id}/images/upload/{form_token}',
	'controller\\messages_images_upload::messages_edit_images_upload')
	->assert('form_token', cnst_assert::TOKEN)
	->bind('messages_edit_images_upload');

$c_system_user->post('/messages/images/upload/{form_token}',
	'controller\\messages_images_upload::messages_add_images_upload')
	->assert('form_token', cnst_assert::TOKEN)
	->bind('messages_add_images_upload');

$c_system_user->post('/messages/{id}/images/upload',
	'controller\\messages_images_upload::messages_images_upload')
	->bind('messages_images_upload');

$c_system_user->get('/messages/{id}/extend/{days}',
		'controller\\messages_extend::messages_extend')
	->bind('messages_extend');

$c_system_user->match('/messages/{id}/del',
		'controller\\messages_del::messages_del')
	->bind('messages_del');

$c_system_user->match('/messages/{id}/edit',
		'controller\\messages_edit::messages_edit')
	->bind('messages_edit');

$c_system_user->match('/messages/add',
		'controller\\messages_edit::messages_add')
	->bind('messages_add');

$c_system_guest->match('/messages/{id}',
		'controller\\messages_show::messages_show')
	->bind('messages_show');

$c_system_guest->get('/messages/extended',
		'controller\\messages_extended::messages_extended')
	->bind('messages_extended');

$c_system_guest->match('/messages',
		'controller\\messages_list::messages_list')
	->bind('messages_list');

$c_system_user->match('/users/{id}/image/del',
		'controller\\users_image_del::users_image_del_admin')
	->bind('users_image_del_admin');

$c_system_user->match('/users/image/del',
		'controller\\users_image_del::users_image_del')
	->bind('users_image_del');

$c_system_admin->post('/users/{id}/image/upload',
		'controller\\users_image_upload::users_image_upload_admin')
	->bind('users_image_upload_admin');

$c_system_user->post('/users/image/upload',
		'controller\\users_image_upload::users_image_upload')
	->bind('users_image_upload');

$c_system_admin->match('/users/{id}/password',
		'controller\\users_password::users_password_admin')
	->bind('users_password_admin');

$c_system_user->match('/users/password',
		'controller\\users_password::users_password')
	->bind('users_password');

$c_system_guest->match('/users/{id}/{status}',
		'controller\\users_show::users_show')
	->assert('status', cnst_assert::USER_ACTIVE_STATUS)
	->value('status', 'active')
	->bind('users_show');

$c_system_admin->match('/users/{id}/{status}',
		'controller\\users_show::users_show_admin')
	->assert('status', cnst_assert::USER_STATUS)
	->value('status', 'active')
	->bind('users_show_admin');

$c_system_guest->get('/users/map/{status}',
		'controller\\users_map::users_map')
	->assert('status', cnst_assert::USER_STATUS)
	->value('status', 'active')
	->bind('users_map');

$c_system_admin->match('/users/{id}/del',
		'controller\\users_del_admin::users_del_admin')
	->bind('users_del_admin');

$c_system_admin->match('/users/{id}/edit',
		'controller\\users_edit_admin::users_edit_admin')
	->bind('users_edit_admin');

$c_system_user->match('/users/edit',
		'controller\\users_edit::users_edit')
	->bind('users_edit');

$c_system_admin->match('/users/add',
		'controller\\users_add::users_add')
	->bind('users_add');

$c_system_admin->get('/users/tiles/{status}',
		'controller\\users_tiles::users_tiles_admin')
	->assert('status', cnst_assert::USER_STATUS)
	->value('status', 'active')
	->bind('users_tiles_admin');

$c_system_admin->match('/users/{status}',
		'controller\\users_list::users_list_admin')
	->assert('status', cnst_assert::USER_STATUS)
	->value('status', 'active')
	->bind('users_list_admin');

$c_system_guest->get('/users/tiles/{status}',
		'controller\\users_tiles::users_tiles')
	->assert('status', cnst_assert::USER_ACTIVE_STATUS)
	->value('status', 'active')
	->bind('users_tiles');

$c_system_guest->get('/users/{status}',
		'controller\\users_list::users_list')
	->assert('status', cnst_assert::USER_ACTIVE_STATUS)
	->value('status', 'active')
	->bind('users_list');

$c_system_admin->match('/transactions/{id}/edit',
		'controller\\transactions_edit::transactions_edit')
	->bind('transactions_edit');

$c_system_user->match('/transactions/add',
		'controller\\transactions_add::transactions_add')
	->bind('transactions_add');

$c_system_guest->get('/transactions/{id}',
		'controller\\transactions_show::transactions_show')
	->bind('transactions_show');

$c_system_guest->get('/transactions',
		'controller\\transactions::transactions')
	->bind('transactions');

$c_system_admin->match('/news/{id}/del',
		'controller\\news_del::news_del')
	->bind('news_del');

$c_system_admin->match('/news/{id}/edit',
		'controller\\news_edit::news_edit')
	->bind('news_edit');

$c_system_guest->match('/news/{id}',
		'controller\\news_show::news_show')
	->bind('news_show');

$c_system_user->match('/news/add',
		'controller\\news_add::news_add')
	->bind('news_add');

$c_system_admin->get('/news/{id}/approve',
		'controller\\news_approve::news_approve')
	->bind('news_approve');

$c_system_guest->get('/news/extended',
		'controller\\news::news_extended')
	->bind('news_extended');

$c_system_guest->get('/news',
		'controller\\news::news_list')
	->bind('news_list');

$c_system_admin->match('/docs/{doc_id}/edit',
		'controller\\docs_edit::docs_edit')
	->assert('doc_id', cnst_assert::DOC_ID)
	->bind('docs_edit');

$c_system_admin->match('/docs/{doc_id}/del',
		'controller\\docs_del::docs_del')
	->assert('doc_id', cnst_assert::DOC_ID)
	->bind('docs_del');

$c_system_admin->match('/docs/add/{map_id}',
		'controller\\docs_add::docs_add')
	->assert('map_id', cnst_assert::DOC_MAP_ID)
	->value('map_id', '')
	->bind('docs_add');

$c_system_admin->match('/docs/map/{map_id}/edit',
		'controller\\docs_map_edit::docs_map_edit')
	->assert('map_id', cnst_assert::DOC_MAP_ID)
	->bind('docs_map_edit');

$c_system_guest->get('/docs/map/{map_id}',
		'controller\\docs_map::docs_map')
	->assert('map_id', cnst_assert::DOC_MAP_ID)
	->bind('docs_map');

$c_system_guest->get('/docs',
		'controller\\docs::docs')
	->bind('docs');

$c_system_user->match('/forum/{forum_id}/edit',
		'controller\\forum_edit::forum_edit')
	->assert('forum_id', cnst_assert::FORUM_ID)
	->bind('forum_edit');

$c_system_user->match('/forum/{forum_id}/del',
		'controller\\forum_del::forum_del')
	->assert('forum_id', cnst_assert::FORUM_ID)
	->bind('forum_del');

$c_system_guest->match('/forum/{topic_id}',
		'controller\\forum_topic::forum_topic')
	->assert('topic_id', cnst_assert::FORUM_ID)
	->bind('forum_topic');

$c_system_user->match('/forum/add-topic',
		'controller\\forum_add_topic::forum_add_topic')
	->bind('forum_add_topic');

$c_system_guest->get('/forum',
		'controller\\forum::forum')
	->bind('forum');

$c_system_user->get('/typeahead-account-codes',
		'controller\\typeahead_account_codes::typeahead_account_codes')
	->bind('typeahead_account_codes');

$c_system_guest->get('/typeahead-accounts/{status}',
		'controller\\typeahead_accounts::typeahead_accounts')
	->assert('status', cnst_assert::USER_PRIMARY_STATUS)
	->bind('typeahead_accounts');

$c_system_admin->get('/typeahead-doc-map-names',
		'controller\\typeahead_doc_map_names::typeahead_doc_map_names')
	->bind('typeahead_doc_map_names');

$c_system_user->get('/typeahead-eland-intersystem-accounts/{remote_schema}',
		'controller\\typeahead_eland_intersystem_accounts::typeahead_eland_intersystem_accounts')
	->assert('remote_schema', cnst_assert::SCHEMA)
	->bind('typeahead_eland_intersystem_accounts');

$c_system_user->get('/typeahead-elas-intersystem-accounts/{group_id}',
		'controller\\typeahead_elas_intersystem_accounts::typeahead_elas_intersystem_accounts')
	->assert('group_id', cnst_assert::NUMBER)
	->bind('typeahead_elas_intersystem_accounts');

$c_system_admin->get('/typeahead-log-types',
		'controller\\typeahead_log_types::typeahead_log_types')
	->bind('typeahead_log_types');

$c_system_user->get('/typeahead-postcodes',
		'controller\\typeahead_postcodes::typeahead_postcodes')
	->bind('typeahead_postcodes');

$c_system_admin->get('/typeahead-usernames',
		'controller\\typeahead_usernames::typeahead_usernames')
	->bind('typeahead_usernames');

$c_system_guest->get('/elas-group-login/{group_id}',
		'controller\\elas_group_login::elas_group_login')
	->assert('group_id', cnst_assert::NUMBER)
	->bind('elas_group_login');

$c_system_admin->get('/elas-soap-status/{group_id}',
		'controller\\elas_soap_status::elas_soap_status')
	->assert('group_id', cnst_assert::NUMBER)
	->bind('elas_soap_status');

$c_system_guest->get('/plot-user-transactions/{user_id}/{days}',
		'controller\\plot_user_transactions::plot_user_transactions')
	->bind('plot_user_transactions');

$c_system_admin->get('/transactions-sum-in/{days}',
		'controller\\transactions_sum::transactions_sum_in')
	->bind('transactions_sum_in');

$c_system_admin->get('/transactions-sum-out/{days}',
		'controller\\transactions_sum::transactions_sum_out')
	->bind('transactions_sum_out');

$c_system_admin->get('/weighted-balances/{days}',
		'controller\\weighted_balances::weighted_balances')
	->bind('weighted_balances');

$c_system_init->get('/elas-db-upgrade',
	'controller\\init::elas_db_upgrade')
	->bind('init_elas_db_upgrade');

$c_system_init->get('/sync-users-images/{start}',
	'controller\\init::sync_users_images')
	->value('start', 0)
	->bind('init_sync_users_images');

$c_system_init->get('/sync-messages-images/{start}',
	'controller\\init::sync_messages_images')
	->value('start', 0)
	->bind('init_sync_messages_images');

$c_system_init->get('/clear-users-cache',
	'controller\\init::clear_users_cache')
	->bind('init_clear_users_cache');

$c_system_init->get('/empty-elas-tokens',
	'controller\\init::empty_elas_tokens')
	->bind('init_empty_elas_tokens');

$c_system_init->get('/empty-city-distance',
	'controller\\init::empty_city_distance')
	->bind('init_empty_city_distance');

$c_system_init->get('/queue-geocoding/{start}',
	'controller\\init::queue_geocoding')
	->value('start', 0)
	->bind('init_queue_geocoding');

$c_system_init->get('/copy-config',
	'controller\\init::copy_config')
	->bind('init_copy_config');

$c_system_init->get('/',
	'controller\\init::init')
	->bind('init');

$c_system_anon->get('/',
	'controller\\home_system::home_system')
	->bind('home_system');

$c_locale->get('/', 'controller\\index::index')
	->bind('index');

$c_system_anon->mount('/{role_short}', $c_system_admin);
$c_system_anon->mount('/{role_short}', $c_system_user);
$c_system_anon->mount('/{role_short}', $c_system_guest);
$c_system_anon->mount('/init', $c_system_init);
$c_locale->mount('/{system}', $c_system_anon);
$app->mount('/', $c_locale);

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
