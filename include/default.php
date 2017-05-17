<?php

require_once __DIR__ . '/../vendor/autoload.php';

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

//	$monolog->pushProcessor(new Monolog\Processor\WebProcessor());

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

/*
$app->register(new Silex\Provider\SecurityServiceProvider(), [

	'security.firewalls' => [

		'unsecured'	=> [
			'anonymous'	=> true,
		],

		'secured'	=> [
			'host'		=> '^l.',
			'users'		=> function () use ($app) {
				return new util\user_provider($app['xdb']);
			},

		],
	],

	'security.role_hierarchy' => [
		'ROLE_ADMIN' => ['ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'],
	],

]);
*/


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

$app['this_group'] = function($app){
	return new service\this_group($app['groups'], $app['db'], $app['predis'], $app['twig']);
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

$app['date_format'] = function(){
	return new service\date_format();
};

$app['mailaddr'] = function ($app){
	return new service\mailaddr($app['db'], $app['monolog'], $app['this_group']);
};

$app['interlets_groups'] = function ($app){
	return new service\interlets_groups($app['db'], $app['predis'], $app['groups'], $app['protocol']);
};

$app['distance'] = function ($app){
	return new service\distance($app['db'], $app['cache']);
};

// queue

$app['queue.mail'] = function ($app){
	return new queue\mail($app['queue'], $app['monolog'],
		$app['this_group'], $app['mailaddr'], $app['twig']);
};

/**
 * functions
 */

function link_user($user, $sch = false, $link = true, $show_id = false, $field = false)
{
	global $rootpath;

	if (!$user)
	{
		return '<i>** leeg **</i>';
	}

	$user = is_array($user) ? $user : readuser($user, false, $sch);
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

/*
 *
 */

function readconfigfromdb($key, $sch = null)
{
    global $app, $s_guest, $s_master;
    static $cache;

	$eland_config_default = [
		'preset_minlimit'					=> '',
		'preset_maxlimit'					=> '',
		'users_can_edit_username'			=> '0',
		'users_can_edit_fullname'			=> '0',
		'registration_en'					=> '0',
		'registration_top_text'				=> '',
		'registration_bottom_text'			=> '',
		'registration_success_text'			=> '',
		'registration_success_url'			=> '',
		'forum_en'							=> '0',
		'css'								=> '0',
		'msgs_days_default'					=> '365',
		'balance_equilibrium'				=> '0',
		'date_format'						=> '%e %b %Y, %H:%M:%S',
		'weekly_mail_show_interlets'		=> 'recent',
		'weekly_mail_show_news'				=> 'recent',
		'weekly_mail_show_docs'				=> 'recent',
		'weekly_mail_show_forum'			=> 'recent',
		'weekly_mail_show_transactions'		=> 'recent',
		'weekly_mail_show_leaving_users'	=> 'recent',
		'weekly_mail_show_new_users'		=> 'recent',
		'weekly_mail_template'				=> 'messages_top',
		'default_landing_page'				=> 'messages',
		'homepage_url'						=> '',
	];

    if (!isset($sch))
    {
		$sch = $app['this_group']->get_schema();
	}

	if (!$sch)
	{
		$app['monolog']->error('no schema set in readconfigfromdb');
	}

	if (isset($cache[$sch][$key]))
	{
		return $cache[$sch][$key];
	}

	$redis_key = $sch . '_config_' . $key;

	if ($app['predis']->exists($redis_key))// && $key != 'date_format')
	{
		return $cache[$sch][$key] = $app['predis']->get($redis_key);
	}

	$row = $app['xdb']->get('setting', $key, $sch);

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
			$app['xdb']->set('setting', $key, ['value' => $value], $sch);
		}
	}

	if (isset($value))
	{
		$app['predis']->set($redis_key, $value);
		$app['predis']->expire($redis_key, 2592000);
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

	$s = ($remote_schema) ?: $app['this_group']->get_schema();

	$redis_key = $s . '_user_' . $id;

	if (!$refresh)
	{
		if (isset($cache[$s][$id]))
		{
			return $cache[$s][$id];
		}

		if ($app['predis']->exists($redis_key))
		{
			return $cache[$s][$id] = unserialize($app['predis']->get($redis_key));
		}
	}

	$user = $app['db']->fetchAssoc('select * from ' . $s . '.users where id = ?', [$id]);

	if (!is_array($user))
	{
		return [];
	}

	// hack eLAS compatibility (in eLAND limits can be null)
	$user['minlimit'] = $user['minlimit'] == -999999999 ? '' : $user['minlimit'];
	$user['maxlimit'] = $user['maxlimit'] == 999999999 ? '' : $user['maxlimit'];

	$row = $app['xdb']->get('user_fullname_access', $id, $s);

	if ($row)
	{
		$user += ['fullname_access' => $row['data']['fullname_access']];
	}
	else
	{
		$user += ['fullname_access' => 'admin'];

		$app['xdb']->set('user_fullname_access', $id, ['fullname_access' => 'admin'], $s);
	}

	if (isset($user))
	{
		$app['predis']->set($redis_key, serialize($user));
		$app['predis']->expire($redis_key, 2592000);
		$cache[$s][$id] = $user;
	}

	return $user;
}
