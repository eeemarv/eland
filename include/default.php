<?php

$app = new util\app();

if(!isset($rootpath))
{
	$rootpath = './';
}

$app['rootpath'] = $rootpath;

$app['debug'] = getenv('DEBUG');
$app['route_class'] = 'util\route';
$app['protocol'] = getenv('ELAND_HTTPS') ? 'https://' : 'http://';
$app['overall_domain'] = getenv('OVERALL_DOMAIN');
$app['s3_bucket'] = getenv('AWS_S3_BUCKET');
$app['s3_region'] = getenv('AWS_S3_REGION');
$app['s3_url'] = 'https://s3.' . $app['s3_region'] . '.amazonaws.com/' . $app['s3_bucket'] . '/';

setlocale(LC_TIME, 'nl_NL.UTF-8');

date_default_timezone_set((getenv('TIMEZONE')) ?: 'Europe/Brussels');

$app->register(new Predis\Silex\ClientServiceProvider(), [
	'predis.parameters' => getenv('REDIS_URL'),
	'predis.options'    => [
		'prefix'  => 'eland_',
	],
]);

$app->register(new Silex\Provider\DoctrineServiceProvider(), [
    'db.options' => [
        'url'   => getenv('DATABASE_URL'),
    ],
]);

$app->register(new Silex\Provider\TwigServiceProvider(), [
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
	$twig->addExtension(new \twig\extension());
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

$app->register(new Silex\Provider\LocaleServiceProvider());

$app->register(new Silex\Provider\TranslationServiceProvider(), [
    'locale_fallbacks' 	=> ['nl'],
    'locale'			=> 'nl',
]);

$app->extend('translator', function($translator, $app) {

	$translator->addLoader('yaml', new \Symfony\Component\Translation\Loader\YamlFileLoader());

	$trans_dir = __DIR__ . '/../translation/';

//	$translator->addResource('yaml', $trans_dir . 'messages.en.yaml', 'en');
	$translator->addResource('yaml', $trans_dir . 'messages.nl.yaml', 'nl');
	$translator->addResource('yaml', $trans_dir . 'mail.nl.yaml', 'nl', 'mail');

	return $translator;
});

$app->register(new Silex\Provider\MonologServiceProvider(), []);

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

		if (isset($app['s_schema']))
		{
			$record['extra']['user_schema'] = $app['s_schema'];
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

$app['s3'] = function($app){
	return new service\s3(
		$app['s3_bucket'],
		$app['s3_region']
	);
};

$app['typeahead'] = function($app){
	return new service\typeahead(
		$app['predis'],
		$app['monolog']
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
		$app['config']
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
		$app['overall_domain']
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
		$app['config'],
		$app['protocol']
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
		$app['email_validate']
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
		$app['systems']
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
		$app['mail_addr_user']
	);
};

$app['schema_task.interlets_fetch'] = function ($app){
	return new schema_task\interlets_fetch(
		$app['predis'],
		$app['db'],
		$app['xdb'],
		$app['cache'],
		$app['typeahead'],
		$app['monolog'],
		$app['schedule'],
		$app['systems']
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
		$app['geocode']
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
		'cookie_domain'				=> '.' . getenv('OVERALL_DOMAIN'),
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

$app['pagination'] = function ($app){
	return new service\pagination(
		$app['select']
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
		$app['user_cache']);
};

/**
 * render
 */

$app['link'] = function ($app){
	return new render\link(
		$app['url_generator']
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
		$app['predis'],
		$app['script_name']
	);
};

$app['access_control'] = function($app){
	return new service\access_control(
		$app['tschema'],
		$app['config'],
		$app['s_access_level']
	);
};

/**
 * functions
 */

function link_user($user, string $sch, $link = true, $show_id = false, $field = ''):string
{
	global $app;

	if (!$user)
	{
		return '<i>** leeg **</i>';
	}

	$user = is_array($user) ? $user : $app['user_cache']->get($user, $sch);
	$str = $field ? $user[$field] : $user['letscode'] . ' ' . $user['name'];
	$str = trim($str) === '' ? '<i>** leeg **</i>' : htmlspecialchars($str, ENT_QUOTES);

	if ($link)
	{
		$param = ['id' => $user['id']];

		if (is_string($link))
		{
			$param['link'] = $link;
		}

		$out = '<a href="';
		$out .= generate_url('users', $param, $sch);
		$out .= '">' . $str . '</a>';
	}
	else
	{
		$out = $str;
	}

	$out .= $show_id ? ' (id: ' . $user['id'] . ')' : '';

	return $out;
}
