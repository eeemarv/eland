<?php declare(strict_types=1);

use util\app;
use Silex\Provider;
use Knp\Provider\ConsoleServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use cnst\pages as cnst_pages;
use cnst\role as cnst_role;

$app = new app();

$app['debug'] = getenv('DEBUG');
$app['route_class'] = 'util\route';
$app['legacy_eland_origin_pattern'] = getenv('LEGACY_ELAND_ORIGIN_PATTERN');
$app['overall_domain'] = getenv('OVERALL_DOMAIN');
$app['s3_bucket'] = getenv('AWS_S3_BUCKET');
$app['s3_region'] = getenv('AWS_S3_REGION');
$app['s3_url'] = 'https://s3.' . $app['s3_region'] . '.amazonaws.com/' . $app['s3_bucket'] . '/';
$app['mapbox_token'] = getenv('MAPBOX_TOKEN');
$app['log_schema_en'] = false;

$app->register(new Predis\Silex\ClientServiceProvider(), [
	'predis.parameters' => getenv('REDIS_URL'),
	'predis.options'    => [
		'prefix'  => 'eland_',
	],
]);

$app->register(new Provider\DoctrineServiceProvider(), [
    'db.options' => [
        'url'   => getenv('DATABASE_URL'),
    ],
]);

$app->register(new Provider\TwigServiceProvider(), [
	'twig.path' => __DIR__ . '/../view',
	'twig.options'	=> [
		'cache'		=> __DIR__ . '/../cache',
		'debug'		=> getenv('DEBUG'),
	],
	'twig.form.templates'	=> [
		'bootstrap_3_layout.html.twig',
	],
]);

$app->extend('twig', function($twig, $app) {

	$twig->addGlobal('s3_url', $app['s3_url']);
	$twig->addExtension(new twig\extension());
	$twig->addRuntimeLoader(new \Twig_FactoryRuntimeLoader([
		twig\config::class => function() use ($app){
			return new twig\config($app['config']);
		},
		twig\date_format::class => function() use ($app){
			return new twig\date_format($app['date_format']);
		},
		twig\alert::class => function() use ($app){
			return new twig\alert($app['alert']);
		},
		twig\assets::class => function() use ($app){
			return new twig\assets($app['assets']);
		},
		twig\account::class => function() use ($app){
			return new twig\account($app['user_cache']);
		},
		twig\mpp_ary::class => function() use ($app){
			return new twig\mpp_ary($app['user_cache'], $app['systems']);
		},
		twig\link_url::class => function() use ($app){
			return new twig\link_url($app['url_generator']);
		},
		twig\system::class => function() use ($app){
			return new twig\system($app['systems']);
		},
		twig\base_url::class => function() use ($app){
			return new twig\base_url(
				$app['systems'],
				$app['protocol']
			);
		},
		twig\s3_url::class => function() use ($app){
			return new twig\s3_url(
				$app['s3_url']
			);
		},
		twig\heading::class => function() use ($app){
			return new twig\heading(
				$app['heading']
			);
		},
		twig\btn_nav::class => function() use ($app){
			return new twig\btn_nav(
				$app['btn_nav']
			);
		},
		twig\btn_top::class => function() use ($app){
			return new twig\btn_top(
				$app['btn_top']
			);
		},
		twig\pagination::class => function() use ($app){
			return new twig\pagination(
				$app['pagination']
			);
		},
		twig\pp_role::class => function() use ($app){
			return new twig\pp_role(
				$app['pp_anonymous'],
				$app['pp_guest'],
				$app['pp_user'],
				$app['pp_admin'],
				$app['s_master'],
				$app['s_elas_guest']
			);
		},
		twig\s_role::class => function() use ($app){
			return new twig\s_role($app['s_role'], $app['s_id'],
				$app['s_schema'], $app['s_master'], $app['s_elas_guest'],
				$app['s_system_self']
			);
		},
		twig\pp_ary::class => function() use ($app){
			return new twig\pp_ary($app['pp_ary']);
		},
		twig\r_default::class => function() use ($app){
			return new twig\r_default($app['r_default']);
		},
		twig\menu::class => function() use ($app){
			return new twig\menu($app['menu']);
		},
		twig\menu_nav_user::class => function() use ($app){
			return new twig\menu_nav_user($app['menu_nav_user']);
		},
		twig\menu_nav_system::class => function() use ($app){
			return new twig\menu_nav_system($app['menu_nav_system']);
		},
	]));

	return $twig;
});

$app->register(new Provider\LocaleServiceProvider());

$app->register(new Provider\TranslationServiceProvider(), [
    'locale_fallbacks' 	=> ['nl'],
    'locale'			=> 'nl',
]);

$app->extend('translator', function($translator, $app) {

	$translator->addLoader('yaml', new Symfony\Component\Translation\Loader\YamlFileLoader());

	$trans_dir = __DIR__ . '/../translation/';

//	$translator->addResource('yaml', $trans_dir . 'messages.en.yaml', 'en');
	$translator->addResource('yaml', $trans_dir . 'messages.nl.yaml', 'nl');
	$translator->addResource('yaml', $trans_dir . 'mail.nl.yaml', 'nl', 'mail');

	return $translator;
});

$app->register(new Provider\MonologServiceProvider(), []);

$app->extend('monolog', function($monolog, $app) {

	$monolog->setTimezone(new DateTimeZone('UTC'));

	$handler = new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG);
	$handler->setFormatter(new \Bramus\Monolog\Formatter\ColoredLineFormatter());
	$monolog->pushHandler($handler);

	$handler = new \Monolog\Handler\RedisHandler($app['predis'], 'monolog_logs', \Monolog\Logger::DEBUG, true, 20);
	$handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
	$monolog->pushHandler($handler);

	$monolog->pushProcessor(function ($record) use ($app){

		if ($app['log_schema_en'])
		{
			/*
			$request = $app['request_stack']->getCurrentRequest();

			if ($request
				&& $request->attributes->get('schema')
				&& $request->attributes->get('role_short'))
			{
				$record['extra']['user_schema'] = $app['s_schema'];
				$record['extra']['user_id'] = $app['s_id'];
			}
			*/

			$record['extra']['schema'] = $app['pp_schema'];
			$record['extra']['user_schema'] = $app['s_schema'];
			$record['extra']['user_id'] = $app['s_id'];
		}


		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');

		if ($ip)
		{
			$record['extra']['ip'] = $ip;
		}

		return $record;
	});

	return $monolog;
});

if ($app['debug'])
{
	$app->register(new Provider\WebProfilerServiceProvider(), array(
		'profiler.cache_dir' 	=> __DIR__.'/../cache/profiler',
		'profiler.mount_prefix'	=> '/_profiler',
	));
}

$app->register(new Provider\HttpFragmentServiceProvider());
$app->register(new Provider\ServiceControllerServiceProvider());
$app->register(new ConsoleServiceProvider());

$app->error(function (\Exception $e, Request $request, $code) use ($app) {
	if ($app['debug'])
	{
        return;
	}

	// to do
	$app['heading']->add((string) $code);
	$app['menu']->set('contacts');

	return $app->render('base/base.html.twig', [

	]);
});

/**
 *
 */

$app['new_user_treshold'] = function ($app):int{
	$new_user_days = (int) $app['config']->get('newuserdays', $app['pp_schema']);
	return time() -  ($new_user_days * 86400);
};

$app['s_view'] = function ($app):array{

	$s_view = $app['session']->get('view') ?? cnst_pages::DEFAULT_VIEW;
	$route = $app['request']->attributes->get('_route');

	if (isset(cnst_pages::ROUTE_TO_VIEW[$route]))
	{
		[$menu, $view] = cnst_pages::ROUTE_TO_VIEW[$route];

		if ($s_view[$menu] !== $view)
		{
			$s_view[$menu] = $view;
			$app['session']->set('view', $s_view);
		}
	}

	return $s_view;
};

$app['r_users'] = function ($app):string{

	if ($app['s_view']['users'] === 'map')
	{
		return 'users_map';
	}

	$route = 'users_';
	$route .= $app['s_view']['users'];

	if ($app['pp_role'] === 'admin')
	{
		$route .= '_admin';
	}

	return $route;
};

$app['r_users_show'] = function ($app):string{
	return 'users_show' . ($app['pp_admin'] ? '_admin' : '');
};

$app['r_users_edit'] = function ($app):string{
	return 'users_edit' . ($app['pp_admin'] ? '_admin' : '');
};

$app['r_messages'] = function ($app):string{
	return 'messages_' . $app['s_view']['messages'];
};

$app['r_news'] = function ($app):string{
	return 'news_' . $app['s_view']['news'];
};

$app['r_default'] = function ($app):string{

	$route = $app['config']->get('default_landing_page', $app['pp_schema']);

	switch ($route)
	{
		case 'users':
			return $app['r_users'];
		case 'messages':
			return $app['r_messages'];
		case 'news':
			return $app['r_news'];
	}

	return $route;
};

$app['intersystem_en'] = function($app):bool{
	return $app['config']->get('template_lets', $app['pp_schema'])
		&& $app['config']->get('interlets_en', $app['pp_schema']);
};

$app['pp_role_short'] = function ($app):string{
	return $app['request']->attributes->get('role_short', '');
};

$app['pp_role'] =  function ($app):string{
	return cnst_role::LONG[$app['pp_role_short']] ?? 'anonymous';
};

$app['pp_system'] = function ($app):string{
	return $app['request']->attributes->get('system', '');
};

$app['pp_org_system'] = function ($app):string{
	$pp_org_system = $app['request']->query->get('org_system', '');

	if ($pp_org_system === $app['pp_system'])
	{
		return '';
	}

	if (!$app['systems']->get_schema($pp_org_system))
	{
		return '';
	}

	return $pp_org_system;
};

$app['pp_ary'] = function ($app):array{

	$pp_ary = [];

	if ($app['pp_system'] !== '')
	{
		$pp_ary['system'] = $app['pp_system'];

		if ($app['pp_role_short'] !== '')
		{
			if (!isset(cnst_role::LONG[$app['pp_role_short']]))
			{
				return [];
			}

			$pp_ary['role_short'] = $app['pp_role_short'];

			if ($app['pp_org_system'] !== '')
			{
				$pp_ary['org_system'] = $app['pp_org_system'];
			}
		}
	}

	return $pp_ary;
};

$app['pp_schema'] = function ($app):string{
	return $app['systems']->get_schema($app['pp_system']);
};

$app['request'] = function ($app):Request{
	return $app['request_stack']->getCurrentRequest();
};

$app['s_schema'] = function ($app):string{

	if ($app['pp_org_system'])
	{
		return $app['systems']->get_schema($app['pp_org_system']);
	}

	return $app['pp_schema'];
};

$app['s_system'] = function ($app){
	return $app['systems']->get_system($app['s_schema']);
};

$app['s_ary'] = function ($app){
	if ($app['s_role_short'] === '')
	{
		return [];
	}

	if ($app['s_system'] === '')
	{
		return [];
	}

	return [
		'system'		=> $app['s_system'],
		'role_short'	=> $app['s_role_short']
	];
};

$app['s_system_self'] = function ($app):bool{
	return $app['s_schema'] === $app['pp_schema'];
};

$app['s_logins'] = function ($app):array{
	return $app['session']->get('logins') ?? [];
};

$app['s_id'] = function ($app):int{

	$s_id = $app['s_logins'][$app['s_schema']] ?? 0;

	if (ctype_digit((string) $s_id))
	{
		return $s_id;
	}

	return 0;
};

$app['session_user'] = function ($app):array{

	if (!$app['s_id'])
	{
		return [];
	}

	return $app['user_cache']->get($app['s_id'], $app['s_schema']);
};

$app['s_role'] = function ($app):string{

	if ($app['s_master'])
	{
		return 'admin';
	}

	$role = $app['session_user']['accountrole'] ?? 'anonymous';

	if ($role === 'interlets')
	{
		return 'anonymous';
	}

	if ($role === 'admin')
	{
		return 'admin';
	}

	if ($role === 'user')
	{
		return 'user';
	}

	return 'anonymous';
};

$app['s_role_short'] = function ($app):string{
	if ($app['s_role'] === 'user')
	{
		return 'u';
	}

	if ($app['s_role'] === 'admin')
	{
		return 'a';
	}

	return '';
};

$app['pp_guest'] = function ($app):bool{
	return $app['pp_role'] == 'guest';
};

$app['pp_admin'] = function ($app):bool{
	return $app['pp_role'] === 'admin';
};

$app['pp_user'] = function ($app):bool{
	return $app['pp_role'] === 'user';
};

$app['pp_anonymous'] = function ($app):bool{
	return $app['pp_role'] === 'anonymous';
};

$app['s_master'] = function ($app):bool{

	if (isset($app['s_logins'][$app['s_schema']]))
	{
		return $app['s_logins'][$app['s_schema']] === 'master';
	}

	return false;
};

$app['s_elas_guest'] = function ($app):bool{

	if (!$app['s_system_self'])
	{
		return false;
	}

	if (isset($app['s_logins'][$app['pp_schema']]))
	{
		return $app['s_logins'][$app['pp_schema']] === 'elas';
	}

	return false;
};

$app['welcome_msg'] = function (app $app):string{
	$msg = '<strong>Welkom bij ';
	$msg .= $app['config']->get('systemname', $app['pp_schema']);
	$msg .= '</strong><br>';
	$msg .= 'Waardering bij ';
	$msg .= $app['config']->get('systemname', $app['pp_schema']);
	$msg .= ' gebeurt met \'';
	$msg .= $app['config']->get('currency', $app['pp_schema']);
	$msg .= '\'. ';
	$msg .= $app['config']->get('currencyratio', $app['pp_schema']);
	$msg .= ' ';
	$msg .= $app['config']->get('currency', $app['pp_schema']);
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

	return $msg;
};

/**
 *
 */

$app['s3'] = function($app){
	return new service\s3(
		$app['s3_bucket'],
		$app['s3_region']
	);
};

$app['image_upload'] = function($app){
	return new service\image_upload(
		$app['monolog'],
		$app['s3']
	);
};

$app['typeahead'] = function($app){
	return new service\typeahead(
		$app['predis'],
		$app['monolog'],
		$app['url_generator'],
		$app['systems'],
		$app['assets']
	);
};

$app['thumbprint_accounts'] = function($app){
	return new service\thumbprint_accounts(
		$app['typeahead'],
		$app['intersystems']
	);
};

$app['log_db'] = function($app){
	return new service\log_db(
		$app['db'],
		$app['predis']
	);
};

$app['transaction'] = function($app){
	return new service\transaction(
		$app['db'],
		$app['monolog'],
		$app['user_cache'],
		$app['autominlimit'],
		$app['config'],
		$app['account']
	);
};

$app['mail_transaction'] = function($app){
	return new service\mail_transaction(
		$app['user_cache'],
		$app['config'],
		$app['mail_addr_system'],
		$app['mail_addr_user'],
		$app['queue.mail']
	);
};

/**
 * Get all schemas, systems and domains on this server
 */

$app['systems'] = function ($app){
	return new service\systems(
		$app['db'],
		$app['legacy_eland_origin_pattern']
	);
};

$app['xdb'] = function ($app){
	return new service\xdb(
		$app['db'],
		$app['monolog']
	);
};

$app['cache'] = function ($app){
	return new service\cache(
		$app['db'],
		$app['predis'],
		$app['monolog']
	);
};

$app['queue'] = function ($app){
	return new service\queue(
		$app['db'],
		$app['monolog']
	);
};

$app['date_format'] = function($app){
	return new service\date_format(
		$app['config']
	);
};

$app['mail_addr_system'] = function ($app){
	return new service\mail_addr_system(
		$app['monolog'],
		$app['config']
	);
};

$app['mail_addr_user'] = function ($app){
	return new service\mail_addr_user(
		$app['db'],
		$app['monolog']
	);
};

$app['intersystems'] = function ($app){
	return new service\intersystems(
		$app['db'],
		$app['predis'],
		$app['systems'],
		$app['config']
	);
};

$app['distance'] = function ($app){
	return new service\distance(
		$app['db'],
		$app['cache']
	);
};

$app['config'] = function ($app){
	return new service\config(
		$app['db'],
		$app['xdb'],
		$app['predis']
	);
};

$app['user_cache'] = function ($app){
	return new service\user_cache(
		$app['db'],
		$app['xdb'],
		$app['predis']
	);
};

$app['token'] = function ($app){
	return new service\token();
};

$app['email_validate'] = function ($app){
	return new service\email_validate(
		$app['cache'],
		$app['xdb'],
		$app['token'],
		$app['monolog']
	);
};

// queue

$app['queue.mail'] = function ($app){
	return new queue\mail(
		$app['queue'],
		$app['monolog'],
		$app['twig'],
		$app['config'],
		$app['mail_addr_system'],
		$app['email_validate'],
		$app['systems']
	);
};

// tasks for background processes

$app['task.cleanup_images'] = function ($app){
	return new task\cleanup_images(
		$app['cache'],
		$app['db'],
		$app['monolog'],
		$app['s3'],
		$app['systems']
	);
};

$app['task.get_elas_intersystem_domains'] = function ($app){
	return new task\get_elas_intersystem_domains(
		$app['db'],
		$app['cache'],
		$app['systems']
	);
};

$app['task.fetch_elas_intersystem'] = function ($app){
	return new task\fetch_elas_intersystem(
		$app['cache'],
		$app['predis'],
		$app['typeahead'],
		$app['monolog']
	);
};

// schema tasks (tasks applied to every system seperate)

$app['schema_task.cleanup_messages'] = function ($app){
	return new schema_task\cleanup_messages(
		$app['db'],
		$app['monolog'],
		$app['schedule'],
		$app['systems'],
		$app['config']
	);
};

$app['schema_task.cleanup_news'] = function ($app){
	return new schema_task\cleanup_news(
		$app['db'],
		$app['xdb'],
		$app['monolog'],
		$app['schedule'],
		$app['systems']
	);
};

$app['schema_task.geocode'] = function ($app){
	return new schema_task\geocode(
		$app['db'],
		$app['cache'],
		$app['monolog'],
		$app['queue.geocode'],
		$app['schedule'],
		$app['systems'],
		$app['account']
	);
};

$app['schema_task.saldo_update'] = function ($app){
	return new schema_task\saldo_update(
		$app['db'],
		$app['monolog'],
		$app['schedule'],
		$app['systems']
	);
};

$app['schema_task.sync_user_cache'] = function ($app){
	return new schema_task\sync_user_cache(
		$app['db'],
		$app['user_cache'],
		$app['schedule'],
		$app['systems']
	);
};

$app['schema_task.user_exp_msgs'] = function ($app){
	return new schema_task\user_exp_msgs(
		$app['db'],
		$app['queue.mail'],
		$app['schedule'],
		$app['systems'],
		$app['config'],
		$app['user_cache'],
		$app['mail_addr_user']
	);
};

$app['schema_task.saldo'] = function ($app){
	return new schema_task\saldo(
		$app['db'],
		$app['xdb'],
		$app['cache'],
		$app['monolog'],
		$app['queue.mail'],
		$app['schedule'],
		$app['systems'],
		$app['intersystems'],
		$app['config'],
		$app['mail_addr_user'],
		$app['account']
	);
};

//

$app['schedule'] = function ($app){
	return new service\schedule(
		$app['cache'],
		$app['predis']
	);
};

$app['monitor_process'] = function ($app) {
	return new service\monitor_process(
		$app['db'],
		$app['predis'],
		$app['cache']
	);
};

// queue

$app['queue.geocode'] = function ($app){
	return new queue\geocode(
		$app['db'],
		$app['cache'],
		$app['queue'],
		$app['monolog'],
		$app['geocode'],
		$app['account_str']
	);
};

$app['geocode'] = function($app){
	return new service\geocode();
};

/**
 * web
 */

$app->register(new Silex\Provider\SessionServiceProvider(), [
	'session.storage.handler'	=> function ($app) {
		return new Predis\Session\Handler(
			$app['predis'],
			['gc_maxlifetime' => 172800]
		);
	},
	'session.storage.options'	=> [
		'name'						=> 'eland',
	],
]);

$app['assets'] = function($app){
	return new service\assets(
		$app['cache']
	);
};

$app['alert'] = function ($app){
	return new service\alert(
		$app['monolog'],
		$app['session'],
		$app['pp_schema']);
};

$app['menu'] = function($app){
	return new service\menu(
		$app['config'],
		$app['item_access'],
		$app['pp_schema'],
		$app['pp_system'],
		$app['intersystem_en'],
		$app['r_messages'],
		$app['r_users'],
		$app['r_news'],
		$app['r_default']
	);
};

$app['menu_nav_user'] = function($app){
	return new service\menu_nav_user(
		$app['s_id'],
		$app['r_messages'],
		$app['r_users_show']
	);
};

$app['menu_nav_system'] = function($app){
	return new service\menu_nav_system(
		$app['intersystems'],
		$app['systems'],
		$app['s_logins'],
		$app['s_schema'],
		$app['pp_schema'],
		$app['intersystem_en'],
		$app['menu'],
		$app['config'],
		$app['user_cache'],
		$app['s_elas_guest']
	);
};

$app['item_access'] = function($app){
	return new service\item_access(
		$app['assets'],
		$app['pp_schema'],
		$app['pp_role'],
		$app['intersystem_en']
	);
};

$app['password_strength'] = function (){
	return new service\password_strength();
};

$app['autominlimit'] = function ($app){
	return new service\autominlimit(
		$app['monolog'],
		$app['xdb'],
		$app['db'],
		$app['config'],
		$app['user_cache'],
		$app['account']
	);
};

/**
 * render
 */

$app['pagination'] = function ($app){
	return new render\pagination(
		$app['select'],
		$app['link']
	);
};

$app['link'] = function ($app){
	return new render\link(
		$app['url_generator']
	);
};

$app['account_str'] = function ($app) {
	return new render\account_str($app['user_cache']);
};

$app['account'] = function ($app) {
	return new render\account(
		$app['link'],
		$app['systems'],
		$app['user_cache'],
		$app['r_users_show']
	);
};

$app['heading'] = function (){
	return new render\heading();
};

$app['btn_nav'] = function ($app){
	return new render\btn_nav(
		$app['link'],
		$app['tag'],
		$app['assets']
	);
};

$app['tag'] = function (){
	return new render\tag();
};

$app['select'] = function (){
	return new render\select();
};

$app['tbl'] = function ($app){
	return new render\tbl(
		$app['link'],
	);
};

$app['btn_top'] = function ($app){
	return new render\btn_top(
		$app['link']
	);
};

$app['render_stat'] = function (){
	return new render\stat();
};

// init

$app['elas_db_upgrade'] = function ($app){
	return new service\elas_db_upgrade($app['db']);
};

$app['form_token'] = function ($app){
	return new service\form_token(
		$app['request'],
		$app['predis'],
		$app['token']
	);
};

$app['captcha'] = function ($app){
	return new service\captcha(
		$app['request'],
		$app['predis'],
		$app['form_token']
	);
};

$app['data_token'] = function ($app){
	return new service\data_token($app['predis'], $app['token']);
};
