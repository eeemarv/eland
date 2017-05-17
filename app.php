<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new util\app();

$app['debug'] = getenv('DEBUG');

$app['route_class'] = 'util\route';

$app->register(new Predis\Silex\ClientServiceProvider(), [
	'predis.parameters' => getenv('REDIS_URL'),
	'predis.options'    => [
		'prefix'  => 'omq_',
	],
]);

$app->register(new Silex\Provider\DoctrineServiceProvider(), [
    'db.options' => [
        'url'   => getenv('DATABASE_URL'),
    ],
]);

$app->register(new Silex\Provider\TwigServiceProvider(), [
	'twig.path' => __DIR__ . '/view',
	'twig.options'	=> [
		'cache'		=> __DIR__ . '/cache',
		'debug'		=> getenv('DEBUG'),
	],
	'twig.form.templates'	=> [
		'bootstrap_3_horizontal_layout.html.twig',
	],
]);

$app->extend('twig', function($twig, $app) {
    $twig->addGlobal('s3_img', getenv('S3_IMG'));
/*    $twig->addGlobal('projects', $app['xdb']->get('projects')); */
    return $twig;
});

$app->register(new Silex\Provider\SecurityServiceProvider(), [

	'security.firewalls' => [

		'unsecured'	=> [
			'anonymous'	=> true,
		],

/*
		'admin' 	=> [
			'pattern' 	=> '^/admin',
			'http'		=> true,
			'users' 	=> [
				'admin' 	=> ['ROLE_ADMIN', getenv('ADMIN_PASSWORD')],
			],
		],
*/
		'secured'	=> [
			'pattern'	=> '^/s/',

			'users'		=> function () use ($app) {
				return new util\user_provider($app['xdb']);
			},

		],
	],

	'security.role_hierarchy' => [
		'ROLE_ADMIN' => ['ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'],
	],

]);

$app->register(new Knp\Provider\ConsoleServiceProvider(), [
    'console.name'              => 'omv',
    'console.version'           => '01',
    'console.project_directory' => __DIR__,
]);

$app->register(new Silex\Provider\FormServiceProvider());

$app->register(new Silex\Provider\CsrfServiceProvider());

$app->register(new Silex\Provider\ValidatorServiceProvider());

$app->register(new Silex\Provider\MonologServiceProvider(), []);

$app->extend('monolog', function($monolog, $app) {

	$monolog->setTimezone(new DateTimeZone('UTC'));

	$handler = new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::DEBUG);
	$handler->setFormatter(new \Bramus\Monolog\Formatter\ColoredLineFormatter());
	$monolog->pushHandler($handler);

	return $monolog;
});

$app->register(new Silex\Provider\AssetServiceProvider(), [
	'assets.version' => '1',
	'assets.version_format' => '%s?v=%s',
	'assets.base_path'	=> '/assets',
]);

$app->register(new Silex\Provider\LocaleServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => ['nl', 'en'],
    'locale'			=> 'nl',
));

use Symfony\Component\Translation\Loader\YamlFileLoader;

$app->extend('translator', function($translator, $app) {

	$translator->addLoader('yaml', new YamlFileLoader());

	$translator->addResource('yaml', __DIR__.'/translations/en.yml', 'en');
	$translator->addResource('yaml', __DIR__.'/translations/nl.yml', 'nl');

	return $translator;
});


$app->register(new Silex\Provider\SessionServiceProvider(), [
	'session.storage.handler'	=> new service\redis_session($app['predis']),
	'session.storage.options'	=> [
		'name'						=> 'omv',
		'cookie_lifetime'			=> 172800,
	],
]);

$app['xdb'] = function($app){
	return new service\xdb($app['db'], $app['predis'], $app['monolog']);
};

$app['s3'] = function($app){
	return new service\s3($app['monolog']);
};

$app['token'] = function($app){
	return new service\token();
};

$app['uuid'] = function($app){
	return new service\uuid();
};

$app['mail'] = function($app){
	return new service\mail($app['predis']);
};

$app['redis_session'] = function($app){
	return new service\redis_session($app['predis']);
};

/*
$app->register(new Silex\Provider\HttpFragmentServiceProvider());
$app->register(new Silex\Provider\ServiceControllerServiceProvider());

$app->register(new Silex\Provider\WebProfilerServiceProvider(), array(
    'profiler.cache_dir' => __DIR__.'/../cache/profiler',
    'profiler.mount_prefix' => '/_profiler',
));
*/

$app->error(function (\Exception $e, Symfony\Component\HttpFoundation\Request $request, $code) use ($app) {
    if ($app['debug'])
    {
        return;
    }

    // ... logic to handle the error and return a Response

	switch ($code) {
		case 404:
			$message = '404. The requested page could not be found.';
			break;
		default:
			$message =  $code . '. We are sorry, but something went wrong.';
	}

    return new Response($message);
});


return $app;
