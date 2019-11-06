<?php declare(strict_types=1);

use Silex\Provider;
use Knp\Provider\ConsoleServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use App\Cnst\PagesCnst;
use App\Cnst\RoleCnst;

$app = new app();

$app['debug'] = getenv('DEBUG');
$app['route_class'] = 'util\route';
$app['legacy_eland_origin_pattern'] = getenv('LEGACY_ELAND_ORIGIN_PATTERN');
$app['s3_bucket'] = getenv('AWS_S3_BUCKET');
$app['s3_region'] = getenv('AWS_S3_REGION');
$env_s3_url = 'https://s3.' . $app['s3_region'] . '.amazonaws.com/' . $app['s3_bucket'] . '/';
$env_mapbox_token = getenv('MAPBOX_TOKEN');
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

	$twig->addGlobal('s3_url', $env_s3_url);
	$twig->addExtension(new twig\extension());
	$twig->addRuntimeLoader(new \Twig_FactoryRuntimeLoader([
		twig\config::class => function() use ($app){
			return new twig\config($config_service);
		},
		twig\date_format::class => function() use ($app){
			return new twig\date_format($app['date_format']);
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
				$env_s3_url
			);
		},
		twig\heading::class => function() use ($app){
			return new twig\heading(
				$heading_render
			);
		},
		twig\btn_nav::class => function() use ($app){
			return new twig\btn_nav(
				$btn_nav_render
			);
		},
		twig\btn_top::class => function() use ($app){
			return new twig\btn_top(
				$btn_top_render
			);
		},
		twig\pagination::class => function() use ($app){
			return new twig\pagination(
				$app['pagination']
			);
		},
		twig\pp_role::class => function() use ($app){
			return new twig\pp_role(
				$pp->is_anonymous(),
				$pp->is_guest(),
				$pp->is_user(),
				$pp->is_admin(),
				$su->is_master(),
				$su->is_elas_guest()
			);
		},
		twig\s_role::class => function() use ($app){
			return new twig\s_role($app['s_role'], $su->id(),
				$su->schema(), $su->is_master(), $su->is_elas_guest(),
				$su->is_system_self()
			);
		},
		twig\pp_ary::class => function() use ($app){
			return new twig\pp_ary($pp->ary());
		},
		twig\r_default::class => function() use ($app){
			return new twig\r_default($vr->get('default'));
		},
		twig\menu::class => function() use ($app){
			return new twig\menu($menu_service);
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
			$record['extra']['schema'] = $pp->schema();
			$record['extra']['user_schema'] = $su->schema();
			$record['extra']['user_id'] = $su->id();
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

	$assets_service->add(['bootstrap', 'base.css']);

	return $this->render('exception/general.html.twig', [
		'code'		=> $code,
		'message'	=> $e->getMessage(),
	]);
});

/**
 *
 */

$app['s_view'] = function ($app):array{

	$s_view = $session->get('view') ?? PagesCnst::DEFAULT_VIEW;
	$route = $app['request']->attributes->get('_route');

	if (isset(PagesCnst::ROUTE_TO_VIEW[$route]))
	{
		[$menu, $view] = PagesCnst::ROUTE_TO_VIEW[$route];

		if ($s_view[$menu] !== $view)
		{
			$s_view[$menu] = $view;
			$session->set('view', $s_view);
		}
	}

	return $s_view;
};

$vr->get('users') = function ($app):string{

	if ($app['s_view']['users'] === 'map')
	{
		return 'users_map';
	}

	$route = 'users_';
	$route .= $app['s_view']['users'];

	if ($pp->role() === 'admin')
	{
		$route .= '_admin';
	}

	return $route;
};

$vr->get('users_show') = function ($app):string{
	return 'users_show' . ($pp->is_admin() ? '_admin' : '');
};

$vr->get('users_edit') = function ($app):string{
	return 'users_edit' . ($pp->is_admin() ? '_admin' : '');
};

$vr->get('messages') = function ($app):string{
	return 'messages_' . $app['s_view']['messages'];
};

$vr->get('news') = function ($app):string{
	return 'news_' . $app['s_view']['news'];
};

$vr->get('default') = function ($app):string{

	$route = $config_service->get('default_landing_page', $pp->schema());

	switch ($route)
	{
		case 'users':
			return $vr->get('users');
		case 'messages':
			return $vr->get('messages');
		case 'news':
			return $vr->get('news');
	}

	return $route;
};


$app['pp_role_short'] = function ($app):string{
	return $app['request']->attributes->get('role_short', '');
};

$pp->role() =  function ($app):string{
	return RoleCnst::LONG[$app['pp_role_short']] ?? 'anonymous';
};

$pp->system() = function ($app):string{
	return $app['request']->attributes->get('system', '');
};

$pp->org_system() = function ($app):string{
	$pp_org_system = $app['request']->query->get('org_system', '');

	if ($pp_org_system === $pp->system())
	{
		return '';
	}

	if (!$systems_service->get_schema($pp_org_system))
	{
		return '';
	}

	return $pp_org_system;
};

$pp->ary() = function ($app):array{

	$pp_ary = [];

	if ($pp->system() !== '')
	{
		$pp_ary['system'] = $pp->system();

		if ($app['pp_role_short'] !== '')
		{
			if (!isset(RoleCnst::LONG[$app['pp_role_short']]))
			{
				return [];
			}

			$pp_ary['role_short'] = $app['pp_role_short'];

			if ($pp->org_system() !== '')
			{
				$pp_ary['org_system'] = $pp->org_system();
			}
		}
	}

	return $pp_ary;
};

$pp->schema() = function ($app):string{
	return $systems_service->get_schema($pp->system());
};

$app['request'] = function ($app):Request{
	return $app['request_stack']->getCurrentRequest();
};

$su->schema() = function ($app):string{

	if ($pp->org_system())
	{
		return $systems_service->get_schema($pp->org_system());
	}

	return $pp->schema();
};

$app['s_system'] = function ($app){
	return $systems_service->get_system($su->schema());
};

$su->ary() = function ($app){
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

$su->is_system_self() = function ($app):bool{
	return $su->schema() === $pp->schema();
};

$su->logins() = function ($app):array{
	return $session->get('logins') ?? [];
};

$su->id() = function ($app):int{

	$s_id = $su->logins()[$su->schema()] ?? 0;

	if (ctype_digit((string) $s_id))
	{
		return $s_id;
	}

	return 0;
};

$su->user() = function ($app):array{

	if (!$su->id())
	{
		return [];
	}

	return $user_cache_service->get($su->id(), $su->schema());
};

$app['s_role'] = function ($app):string{

	if ($su->is_master())
	{
		return 'admin';
	}

	$role = $su->user()['accountrole'] ?? 'anonymous';

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

$pp->is_guest() = function ($app):bool{
	return $pp->role() == 'guest';
};

$pp->is_admin() = function ($app):bool{
	return $pp->role() === 'admin';
};

$pp->is_user() = function ($app):bool{
	return $pp->role() === 'user';
};

$pp->is_anonymous() = function ($app):bool{
	return $pp->role() === 'anonymous';
};

$su->is_master() = function ($app):bool{

	if (isset($su->logins()[$su->schema()]))
	{
		return $su->logins()[$su->schema()] === 'master';
	}

	return false;
};

$su->is_elas_guest() = function ($app):bool{

	if (!$su->is_system_self())
	{
		return false;
	}

	if (isset($su->logins()[$pp->schema()]))
	{
		return $su->logins()[$pp->schema()] === 'elas';
	}

	return false;
};

$app['welcome_msg'] = function (app $app):string{
	$msg = '<strong>Welkom bij ';
	$msg .= $config_service->get('systemname', $pp->schema());
	$msg .= '</strong><br>';
	$msg .= 'Waardering bij ';
	$msg .= $config_service->get('systemname', $pp->schema());
	$msg .= ' gebeurt met \'';
	$msg .= $config_service->get('currency', $pp->schema());
	$msg .= '\'. ';
	$msg .= $config_service->get('currencyratio', $pp->schema());
	$msg .= ' ';
	$msg .= $config_service->get('currency', $pp->schema());
	$msg .= ' stemt overeen met 1 uur.<br>';

	if ($su->is_elas_guest())
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

$s3_service = function($app){
	return new service\s3(
		$app['s3_bucket'],
		$app['s3_region']
	);
};

$app['image_upload'] = function($app){
	return new service\image_upload(
		$logger,
		$s3_service
	);
};

$app['typeahead'] = function($app){
	return new service\typeahead(
		$app['predis'],
		$logger,
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
		$db,
		$app['predis']
	);
};

$app['transaction'] = function($app){
	return new service\transaction(
		$db,
		$logger,
		$app['user_cache'],
		$autominlimit_service,
		$config_service,
		$app['account']
	);
};

$app['mail_transaction'] = function($app){
	return new service\mail_transaction(
		$app['user_cache'],
		$config_service,
		$mail_addr_system_service,
		$mail_addr_user_service,
		$mail_queue
	);
};

/**
 * Get all schemas, systems and domains on this server
 */

$app['systems'] = function ($app){
	return new service\systems(
		$db,
		$app['legacy_eland_origin_pattern']
	);
};

$xdb_service = function ($app){
	return new service\xdb(
		$db,
		$logger
	);
};

$app['cache'] = function ($app){
	return new service\cache(
		$db,
		$app['predis'],
		$logger
	);
};

$app['queue'] = function ($app){
	return new service\queue(
		$db,
		$logger
	);
};

$app['date_format'] = function($app){
	return new service\date_format(
		$config_service
	);
};

$mail_addr_system_service = function ($app){
	return new service\mail_addr_system(
		$logger,
		$config_service
	);
};

$mail_addr_user_service = function ($app){
	return new service\mail_addr_user(
		$db,
		$logger
	);
};

$app['intersystems'] = function ($app){
	return new service\intersystems(
		$db,
		$app['predis'],
		$app['systems'],
		$config_service
	);
};

$distance_service = function ($app){
	return new service\distance(
		$db,
		$app['cache']
	);
};

$config_service = function ($app){
	return new service\config(
		$app['xdb'],
		$app['predis']
	);
};

$app['user_cache'] = function ($app){
	return new service\user_cache(
		$db,
		$xdb_service,
		$app['predis']
	);
};

$app['token'] = function ($app){
	return new service\token();
};

$app['email_validate'] = function ($app){
	return new service\email_validate(
		$app['cache'],
		$xdb_service,
		$app['token'],
		$logger
	);
};

// queue

$mail_queue = function ($app){
	return new queue\mail(
		$app['queue'],
		$logger,
		$app['twig'],
		$config_service,
		$mail_addr_system_service,
		$app['email_validate'],
		$app['systems']
	);
};

// tasks for background processes

$app['task.cleanup_images'] = function ($app){
	return new task\cleanup_images(
		$app['cache'],
		$db,
		$logger,
		$s3_service,
		$app['systems']
	);
};

$app['task.get_elas_intersystem_domains'] = function ($app){
	return new task\get_elas_intersystem_domains(
		$db,
		$app['cache'],
		$app['systems']
	);
};

$app['task.fetch_elas_intersystem'] = function ($app){
	return new task\fetch_elas_intersystem(
		$app['cache'],
		$app['predis'],
		$app['typeahead'],
		$logger
	);
};

// schema tasks (tasks applied to every system seperate)

$app['schema_task.cleanup_messages'] = function ($app){
	return new schema_task\cleanup_messages(
		$db,
		$logger,
		$app['schedule'],
		$app['systems'],
		$config_service
	);
};

$app['schema_task.cleanup_news'] = function ($app){
	return new schema_task\cleanup_news(
		$db,
		$xdb_service,
		$logger,
		$app['schedule'],
		$app['systems']
	);
};

$app['schema_task.geocode'] = function ($app){
	return new schema_task\geocode(
		$db,
		$app['cache'],
		$logger,
		$app['queue.geocode'],
		$app['schedule'],
		$app['systems'],
		$app['account_str']
	);
};

$app['schema_task.saldo_update'] = function ($app){
	return new schema_task\saldo_update(
		$db,
		$logger,
		$app['schedule'],
		$app['systems']
	);
};

$app['schema_task.sync_user_cache'] = function ($app){
	return new schema_task\sync_user_cache(
		$db,
		$app['user_cache'],
		$app['schedule'],
		$app['systems']
	);
};

$app['schema_task.user_exp_msgs'] = function ($app){
	return new schema_task\user_exp_msgs(
		$db,
		$mail_queue,
		$app['schedule'],
		$app['systems'],
		$config_service,
		$app['user_cache'],
		$mail_addr_user_service
	);
};

$app['schema_task.saldo'] = function ($app){
	return new schema_task\saldo(
		$db,
		$xdb_service,
		$app['cache'],
		$logger,
		$mail_queue,
		$app['schedule'],
		$app['systems'],
		$app['intersystems'],
		$config_service,
		$mail_addr_user_service,
		$app['account_str']
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
		$db,
		$app['predis'],
		$app['cache']
	);
};

// queue

$app['queue.geocode'] = function ($app){
	return new queue\geocode(
		$db,
		$app['cache'],
		$app['queue'],
		$logger,
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

$alert_service = function ($app){
	return new service\alert(
		$app['request'],
		$logger,
		$app['session'],
		$pp->schema());
};

$menu_service = function($app){
	return new service\menu(
		$config_service,
		$item_access_service,
		$pp->schema(),
		$pp->system(),
		$config_service->get_intersystem_en($pp->schema()),
		$vr->get('messages'),
		$vr->get('users'),
		$vr->get('news'),
		$vr->get('default')
	);
};

$app['menu_nav_user'] = function($app){
	return new service\menu_nav_user(
		$su->id(),
		$vr->get('messages'),
		$vr->get('users_show')
	);
};

$app['menu_nav_system'] = function($app){
	return new service\menu_nav_system(
		$app['intersystems'],
		$app['systems'],
		$su->logins(),
		$su->schema(),
		$pp->schema(),
		$config_service->get_intersystem_en($pp->schema()),
		$menu_service,
		$config_service,
		$app['user_cache'],
		$su->is_elas_guest()
	);
};

$item_access_service = function($app){
	return new service\item_access(
		$app['assets'],
		$pp->schema(),
		$pp->role(),
		$config_service->get_intersystem_en($pp->schema())
	);
};

$app['password_strength'] = function (){
	return new service\password_strength();
};

$autominlimit_service = function ($app){
	return new service\autominlimit(
		$logger,
		$xdb_service,
		$db,
		$config_service,
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
		$link_render
	);
};

$link_render = function ($app){
	return new render\link(
		$app['url_generator']
	);
};

$app['account_str'] = function ($app) {
	return new render\account_str($app['user_cache']);
};

$app['account'] = function ($app) {
	return new render\account(
		$link_render,
		$app['systems'],
		$app['user_cache'],
		$vr->get('users_show')
	);
};

$heading_render = function (){
	return new render\heading();
};

$btn_nav_render = function ($app){
	return new render\btn_nav(
		$link_render,
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
		$link_render,
	);
};

$btn_top_render = function ($app){
	return new render\btn_top(
		$link_render
	);
};

$app['render_stat'] = function (){
	return new render\stat();
};

// init

$app['elas_db_upgrade'] = function ($app){
	return new service\elas_db_upgrade($db);
};

$form_token_service = function ($app){
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
		$form_token_service
	);
};

$app['data_token'] = function ($app){
	return new service\data_token($app['predis'], $app['token']);
};
