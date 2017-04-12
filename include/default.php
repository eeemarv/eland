<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();

$app['debug'] = getenv('DEBUG');

$app['eland.protocol'] = getenv('ELAND_HTTPS') ? 'https://' : 'http://';

$app['redis'] = function () {
	try
	{
		$url = getenv('REDIS_URL') ?: getenv('REDISCLOUD_URL');
		$con = parse_url($url);

		if (isset($con['pass']))
		{
			$con['password'] = $con['pass'];
		}

		$con['scheme'] = 'tcp';

		return new Predis\Client($con);
	}
	catch (Exception $e)
	{
		echo 'Couldn\'t connected to Redis: ';
		echo $e->getMessage();
		exit;
	}
};

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
]);

$app->extend('twig', function($twig, $app) {

	$twig->addExtension(new eland\twig_extension($app));

	return $twig;
});

$app->register(new Silex\Provider\MonologServiceProvider(), []);

$app->extend('monolog', function($monolog, $app) {

	$monolog->setTimezone(new DateTimeZone('UTC'));

	$handler = new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG);
	$handler->setFormatter(new \Bramus\Monolog\Formatter\ColoredLineFormatter());
	$monolog->pushHandler($handler);

	$handler = new \Monolog\Handler\RedisHandler($app['redis'], 'monolog_logs', \Monolog\Logger::DEBUG, true, 20);
	$handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
	$monolog->pushHandler($handler);

//	$monolog->pushProcessor(new Monolog\Processor\WebProcessor());

	$monolog->pushProcessor(function ($record) use ($app){

		$record['extra']['schema'] = $app['eland.this_group']->get_schema();

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

$app['eland.rootpath'] = $rootpath;

$app['eland.s3_img'] = getenv('S3_IMG') ?: die('Environment variable S3_IMG S3 bucket for images not defined.');
$app['eland.s3_doc'] = getenv('S3_DOC') ?: die('Environment variable S3_DOC S3 bucket for documents not defined.');

$app['eland.s3_protocol'] = 'http://';

$app['eland.s3_img_url'] = $app['eland.s3_protocol'] . $app['eland.s3_img'] . '/';
$app['eland.s3_doc_url'] = $app['eland.s3_protocol'] . $app['eland.s3_doc'] . '/';

$app['eland.s3'] = function($app){
	return new eland\s3($app['eland.s3_img'], $app['eland.s3_doc']);
};

/*
 * The locale must be installed in the OS for formatting dates.
 */

setlocale(LC_TIME, 'nl_NL.UTF-8');

date_default_timezone_set((getenv('TIMEZONE')) ?: 'Europe/Brussels');

$app['eland.typeahead'] = function($app){
	return new eland\typeahead($app['redis'], $app['monolog']);
};

$app['eland.log_db'] = function($app){
	return new eland\log_db($app['db'], $app['redis']);
};

/**
 * Get all eland schemas and domains
 */

$app['eland.groups'] = function ($app){
	return new eland\groups($app['db']);
};

$app['eland.this_group'] = function($app){
	return new eland\this_group($app['eland.groups'], $app['db'], $app['redis'], $app['twig']);
};

$app['eland.xdb'] = function ($app){
	return new eland\xdb($app['db'], $app['redis'], $app['monolog'], $app['eland.this_group']);
};

$app['eland.cache'] = function ($app){
	return new eland\cache($app['db'], $app['redis'], $app['monolog']);
};

$app['eland.queue'] = function ($app){
	return new eland\queue($app['db'], $app['monolog']);
};

$app['eland.date_format'] = function(){
	return new eland\date_format();
};

$app['eland.mailaddr'] = function ($app){
	return new eland\mailaddr($app['db'], $app['monolog'], $app['eland.this_group']);
};

$app['eland.interlets_groups'] = function ($app){
	return new eland\interlets_groups($app['db'], $app['redis'], $app['eland.groups'], $app['eland.protocol']);
};

$app['eland.distance'] = function ($app){
	return new eland\distance($app['db'], $app['eland.cache']);
};

// queue

$app['eland.queue.mail'] = function ($app){
	return new eland\queue\mail($app['eland.queue'], $app['monolog'],
		$app['eland.this_group'], $app['eland.mailaddr'], $app['twig']);
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
		$sch = $app['eland.this_group']->get_schema();
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

	if ($app['redis']->exists($redis_key))// && $key != 'date_format')
	{
		return $cache[$sch][$key] = $app['redis']->get($redis_key);
	}

	$row = $app['eland.xdb']->get('setting', $key, $sch);

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
			$app['eland.xdb']->set('setting', $key, ['value' => $value], $sch);
		}
	}

	if (isset($value))
	{
		$app['redis']->set($redis_key, $value);
		$app['redis']->expire($redis_key, 2592000);
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

	$s = ($remote_schema) ?: $app['eland.this_group']->get_schema();

	$redis_key = $s . '_user_' . $id;

	if (!$refresh)
	{
		if (isset($cache[$s][$id]))
		{
			return $cache[$s][$id];
		}

		if ($app['redis']->exists($redis_key))
		{
			return $cache[$s][$id] = unserialize($app['redis']->get($redis_key));
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

	$row = $app['eland.xdb']->get('user_fullname_access', $id, $s);

	if ($row)
	{
		$user += ['fullname_access' => $row['data']['fullname_access']];
	}
	else
	{
		$user += ['fullname_access' => 'admin'];

		$app['eland.xdb']->set('user_fullname_access', $id, ['fullname_access' => 'admin'], $s);
	}

	if (isset($user))
	{
		$app['redis']->set($redis_key, serialize($user));
		$app['redis']->expire($redis_key, 2592000);
		$cache[$s][$id] = $user;
	}

	return $user;
}
