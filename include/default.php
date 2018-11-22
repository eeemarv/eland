<?php

$app = new util\app();

$app['debug'] = getenv('DEBUG');

$app['route_class'] = 'util\route';

$app['protocol'] = getenv('ELAND_HTTPS') ? 'https://' : 'http://';

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
		'bootstrap_3_horizontal_layout.html.twig',
	],
]);

$app->extend('twig', function($twig, $app) {

	$twig->addExtension(new service\twig_extension($app));
	$twig->addGlobal('s3_img', getenv('S3_IMG'));
	$twig->addGlobal('s3_doc', getenv('S3_DOC'));

	return $twig;
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

		$record['extra']['schema'] = $app['this_group']->get_schema();

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

		$record['extra']['ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');

		return $record;
	});

	return $monolog;
});

if(!isset($rootpath))
{
	$rootpath = './';
}

$app['rootpath'] = $rootpath;

$app['s3_img'] = getenv('S3_IMG') ?: die('Environment variable S3_IMG S3 bucket for images not defined.');
$app['s3_doc'] = getenv('S3_DOC') ?: die('Environment variable S3_DOC S3 bucket for documents not defined.');

$app['s3_protocol'] = 'http://';

$app['s3_img_url'] = $app['s3_protocol'] . $app['s3_img'] . '/';
$app['s3_doc_url'] = $app['s3_protocol'] . $app['s3_doc'] . '/';

$app['s3'] = function($app){
	return new service\s3($app['s3_img'], $app['s3_doc']);
};

/*
 * The locale must be installed in the OS for formatting dates.
 */

setlocale(LC_TIME, 'nl_NL.UTF-8');

date_default_timezone_set((getenv('TIMEZONE')) ?: 'Europe/Brussels');

$app['typeahead'] = function($app){
	return new service\typeahead($app['predis'], $app['monolog']);
};

$app['log_db'] = function($app){
	return new service\log_db($app['db'], $app['predis']);
};

/**
 * Get all eland schemas and domains
 */

$app['groups'] = function ($app){
	return new service\groups($app['db']);
};

$app['template_vars'] = function ($app){
	return new service\template_vars($app['config']);
};

$app['this_group'] = function($app){
	return new service\this_group($app['groups'], $app['db'], $app['predis']);
};

$app['xdb'] = function ($app){
	return new service\xdb($app['db'], $app['predis'], $app['monolog'], $app['this_group']);
};

$app['cache'] = function ($app){
	return new service\cache($app['db'], $app['predis'], $app['monolog']);
};

$app['queue'] = function ($app){
	return new service\queue($app['db'], $app['monolog']);
};

$app['date_format'] = function($app){
	return new service\date_format($app['config']);
};

$app['mailaddr'] = function ($app){
	return new service\mailaddr($app['db'], $app['monolog'], $app['this_group'], $app['config']);
};

$app['interlets_groups'] = function ($app){
	return new service\interlets_groups($app['db'], $app['predis'], $app['groups'],
		$app['config'], $app['protocol']);
};

$app['distance'] = function ($app){
	return new service\distance($app['db'], $app['cache']);
};

$app['config'] = function ($app){
	return new service\config($app['monolog'], $app['db'], $app['xdb'],
		$app['predis'], $app['this_group']);
};

$app['user_cache'] = function ($app){
	return new service\user_cache($app['db'], $app['xdb'], $app['predis'], $app['this_group']);
};

$app['token'] = function ($app){
	return new service\token();
};

$app['email_validate'] = function ($app){
	return new service\email_validate($app['cache'], $app['xdb'], $app['token'], $app['monolog']);
};


// queue

$app['queue.mail'] = function ($app){
	return new queue\mail($app['queue'], $app['monolog'],
		$app['this_group'], $app['mailaddr'], $app['twig'],
		$app['config'], $app['email_validate']);
};

// tasks

$app['task.cleanup_cache'] = function ($app){
	return new task\cleanup_cache($app['cache'], $app['schedule']);
};

$app['task.cleanup_image_files'] = function ($app){
	return new task\cleanup_image_files($app['cache'], $app['db'], $app['monolog'],
		$app['s3'], $app['groups'], $app['schedule']);
};

$app['task.cleanup_logs'] = function ($app){
	return new task\cleanup_logs($app['db'], $app['schedule']);
};

$app['task.get_elas_interlets_domains'] = function ($app){
	return new task\get_elas_interlets_domains($app['db'], $app['cache'],
		$app['schedule'], $app['groups']);
};

$app['task.fetch_elas_interlets'] = function ($app){
	return new task\fetch_elas_interlets($app['cache'], $app['predis'], $app['typeahead'],
		$app['monolog'], $app['schedule']);
};

// schema tasks (tasks applied to every group seperate)

$app['schema_task.cleanup_messages'] = function ($app){
	return new schema_task\cleanup_messages($app['db'], $app['monolog'],
		$app['schedule'], $app['groups'], $app['this_group'], $app['config']);
};

$app['schema_task.cleanup_news'] = function ($app){
	return new schema_task\cleanup_news($app['db'], $app['xdb'], $app['monolog'],
		$app['schedule'], $app['groups'], $app['this_group']);
};

$app['schema_task.geocode'] = function ($app){
	return new schema_task\geocode($app['db'], $app['cache'],
		$app['monolog'], $app['queue.geocode'],
		$app['schedule'], $app['groups'], $app['this_group']);
};

$app['schema_task.saldo_update'] = function ($app){
	return new schema_task\saldo_update($app['db'], $app['monolog'],
		$app['schedule'], $app['groups'], $app['this_group']);
};

$app['schema_task.sync_user_cache'] = function ($app){
	return new schema_task\sync_user_cache($app['db'], $app['user_cache'],
		$app['schedule'], $app['groups'], $app['this_group']);
};

$app['schema_task.user_exp_msgs'] = function ($app){
	return new schema_task\user_exp_msgs($app['db'], $app['queue.mail'],
		$app['protocol'],
		$app['schedule'], $app['groups'], $app['this_group'],
		$app['config'], $app['template_vars'], $app['user_cache']);
};

$app['schema_task.saldo'] = function ($app){
	return new schema_task\saldo($app['db'], $app['xdb'], $app['predis'], $app['cache'],
		$app['monolog'], $app['queue.mail'],
		$app['s3_img_url'], $app['s3_doc_url'], $app['protocol'],
		$app['date_format'], $app['distance'],
		$app['schedule'], $app['groups'], $app['this_group'],
		$app['interlets_groups'], $app['config']);
};

$app['schema_task.interlets_fetch'] = function ($app){
	return new schema_task\interlets_fetch($app['predis'], $app['db'], $app['xdb'], $app['cache'],
		$app['typeahead'], $app['monolog'],
		$app['schedule'], $app['groups'], $app['this_group']);
};

//

$app['schedule'] = function ($app){
	return new service\schedule($app['cache'], $app['predis']);
};

// queue

$app['queue.geocode'] = function ($app){
	return new queue\geocode($app['db'], $app['cache'],
		$app['queue'], $app['monolog'], $app['user_cache'], $app['geocode']);
};

$app['geocode'] = function($app){
	return new service\geocode();
};

/**
 * functions
 */

function link_user($user, string $sch = '', $link = true, $show_id = false, $field = '')
{
	global $rootpath, $app;

	if (!$user)
	{
		return '<i>** leeg **</i>';
	}

	$user = is_array($user) ? $user : $app['user_cache']->get($user, $sch);
	$str = ($field) ? $user[$field] : $user['letscode'] . ' ' . $user['name'];
	$str = ($str == '' || $str == ' ') ? '<i>** leeg **</i>' : htmlspecialchars($str, ENT_QUOTES);

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

	$out .= ($show_id) ? ' (id: ' . $user['id'] . ')' : '';

	return $out;
}
