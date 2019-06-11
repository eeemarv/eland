<?php

use Silex\Provider;
use Knp\Provider\ConsoleServiceProvider;
use cnst\pages as cnst_pages;
use cnst\role as cnst_role;

$app = new util\app();

$app['debug'] = getenv('DEBUG');
$app['route_class'] = 'util\route';
$app['legacy_eland_origin_pattern'] = getenv('LEGACY_ELAND_ORIGIN_PATTERN');
$app['overall_domain'] = getenv('OVERALL_DOMAIN');
$app['s3_bucket'] = getenv('AWS_S3_BUCKET');
$app['s3_region'] = getenv('AWS_S3_REGION');
$app['s3_url'] = 'https://s3.' . $app['s3_region'] . '.amazonaws.com/' . $app['s3_bucket'] . '/';
$app['mapbox_token'] = getenv('MAPBOX_TOKEN');

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
		twig\account::class => function() use ($app){
			return new twig\account($app['user_cache']);
		},
		twig\user_pp_ary::class => function() use ($app){
			return new twig\user_pp_ary($app['user_cache'], $app['systems']);
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
		twig\mail_url::class => function() use ($app){
			return new twig\mail_url(
				$app['systems'],
				$app['protocol']
			);
		},
		twig\s3_url::class => function() use ($app){
			return new twig\s3_url(
				$app['s3_url']
			);
		},
/*
		twig\distance::class => function() use ($app){
			return new twig\distance(
				$app['db'],
				$app['cache']
			);
		},

		twig\mail_date::class => function() use ($app){
			return new twig\mail_date($app['date_format_cache']);
		},
		twig\web_date::class => function() use ($app){
			return new twig\web_date(
				$app['date_format_cache'],
				$app['request_stack']
			);
		},
		twig\web_user::class => function () use ($app){
			return new twig\web_user(
				$app['user_simple_cache'],
				$app['request_stack'],
				$app['url_generator']
			);
		},
		twig\view::class => function () use ($app){
			return new twig\view($app['view']);
		},
		twig\datepicker::class => function() use ($app){
			return new twig\datepicker($app['web_date'], $app['translator']);
		},
*/
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

		if (isset($app['session_user']) && count($app['session_user']))
		{
			$record['extra']['letscode'] = $app['session_user']['letscode'] ?? '';
			$record['extra']['user_id'] = $app['session_user']['id'] ?? '';
			$record['extra']['username'] = $app['session_user']['name'] ?? '';
		}

/*
		if (isset($app['s_schema']))
		{
			$record['extra']['user_schema'] = $app['s_schema'];
		}
*/

		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');

		if ($ip)
		{
			$record['extra']['ip'] = $app['request']->getClientIp();
			$record['extra']['ip'] = $ip;
		}

		return $record;
	});

	return $monolog;
});

if ($app['debug'])
{
	$app->register(new Provider\WebProfilerServiceProvider(), array(
		'profiler.cache_dir' => __DIR__.'/../cache/profiler',
		'profiler.mount_prefix' => '/_profiler',
	));
}

$app->register(new Provider\HttpFragmentServiceProvider());
$app->register(new Provider\ServiceControllerServiceProvider());

$app->register(new ConsoleServiceProvider());

/**
 *
 */

$app['legacy_route'] = function ($app){
	return new service\legacy_route($app);
};

$app['new_user_treshold'] = function ($app){
	$new_user_days = (int) $app['config']->get('newuserdays', $app['tschema']);
	return time() -  ($new_user_days * 86400);
};

$app['s_view'] = function ($app){

	$s_view = $app['session']->get('view') ?? cnst_pages::DEFAULT_VIEW;
	$view = $app['request']->query->get('view');
	$route = $app['request']->attributes->get('_route');

	if ($view !== null && $view !== $s_view[$route])
	{
		$s_view[$route] = $view;
	}

	return $s_view;
};

$app['intersystem_en'] = function($app){
	return $app['config']->get('template_lets', $app['tschema'])
		&& $app['config']->get('interlets_en', $app['tschema']);
};

$app['pp_role_short'] = function ($app){
	return $app['request']->attributes->get('role_short');
};

$app['pp_role'] =  function ($app){
	return cnst_role::LONG[$app['pp_role_short']];
};

$app['pp_system'] = function ($app){
	return $app['request']->attributes->get('system');
};

$app['pp_ary'] = function ($app){

	if (isset($app['pp_system']))
	{
		if (isset($app['pp_role_short']))
		{
			return [
				'system'		=> $app['pp_system'],
				'role_short'	=> $app['pp_role_short'],
			];
		}

		return [
			'system'	=> $app['pp_system'],
		];
	}

	return [];
};

$app['tschema'] = function ($app){
	return 'x';
	return $app['systems']->get_schema($app['pp_system']);
};

$app['request'] = function ($app){
	return $app['request_stack']->getCurrentRequest();
};

$app['s_schema'] = function ($app){

	if (isset($app['role_short'])
		&& $app['role_short'] === 'g')
	{
		$s_schema = $app['request']->query->get('schema');

		if (isset($s_schema))
		{
			return $s_schema;
		}
	}

	return $app['tschema'];
};

$app['s_system_self'] = function ($app){
	return $app['s_schema'] === $app['tschema'];
};

$app['s_logins'] = function ($app){
	return $app['session']->get('logins') ?? [];
};

$app['s_id'] = function ($app){

	$s_id = $app['s_logins'][$app['s_schema']] ?? 0;

	error_log('S_ID: ' . $s_id);

	if (ctype_digit((string) $s_id))
	{
		return $s_id;
	}

	return 0;
};

$app['session_user'] = function ($app){

	if ($app['s_id'] === 0)
	{
		return [];
	}

	return $app['user_cache']->get($app['s_id'], $app['s_schema']);
};

$app['s_role'] = function ($app){

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

$app['s_guest'] = function ($app){
	return false;
};

$app['s_admin'] = function ($app){
	return $app['s_role'] === 'admin';
};

$app['s_user'] = function ($app){
	return $app['s_role'] === 'user';
};

$app['s_anonymous'] = function ($app){
	return $app['s_role'] === 'anonymous';
};

$app['s_master'] = function ($app){
	if (isset($app['s_logins'][$app['s_schema']]))
	{
		return $app['s_logins'][$app['s_schema']] === 'master';
	}

	return false;
};

$app['s_elas_guest'] = function ($app){
	if (isset($app['s_logins'][$app['s_schema']]))
	{
		return $app['s_logins'][$app['s_schema']] === 'elas';
	}

	return false;
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

$app['typeahead'] = function($app){
	return new service\typeahead(
		$app['predis'],
		$app['monolog'],
		$app['url_generator'],
		$app['systems'],
		$app['assets']
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

// schema tasks (tasks applied to every group seperate)

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
		$app['user_cache'],
		$app['geocode'],
		$app['account']
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
		$app['tschema']);
};

$app['item_access'] = function($app){
	return new service\item_access(
		$app['assets'],
		$app['tschema'],
		$app['s_role'],
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

$app['account'] = function ($app) {
	return new render\account(
		$app['link'],
		$app['systems'],
		$app['user_cache']
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

$app['tpl'] = function ($app){
	return new render\tpl(
		$app['alert'],
		$app['assets'],
		$app['config'],
		$app['systems'],
		$app['intersystems'],
		$app['account'],
		$app['btn_nav'],
		$app['btn_top'],
		$app['heading'],
		$app['link'],
		$app['tschema'],
		$app['s_schema'],
		$app['s_id'],
		$app['pp_ary'],
		$app['session_user'],
		$app['s_logins'],
		$app['s_anonymous'],
		$app['s_guest'],
		$app['s_user'],
		$app['s_admin'],
		$app['s_master'],
		$app['s_elas_guest'],
		$app['s_system_self'],
		$app['intersystem_en']
	);
};

// init

$app['elas_db_upgrade'] = function ($app){
	return new service\elas_db_upgrade($app['db']);
};

$app['form_token'] = function ($app){
	return new service\form_token($app['predis'], $app['token']);
};

$app['data_token'] = function ($app){
	return new service\data_token($app['predis'], $app['token']);
};
