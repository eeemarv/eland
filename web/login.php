<?php declare(strict_types=1);

require_once __DIR__ . '/../include/app.php';

$app->flush();

$token = $app['request']->query->get('token', '');
$login = $app['request']->query->get('login', '');
$location = $app['request']->query->get('location', '');

if (!$location
	|| strpos($location, 'login') !== false
	|| strpos($location, 'logout') !== false
	|| $location === '/')
{
	$location = '';
}

if (strlen($token) > 10)
{
	if($apikey = $app['predis']->get($app['pp_schema'] . '_token_' . $token))
	{
		$app['s_logins'] = array_merge($app['s_logins'], [
			$app['pp_schema'] 	=> 'elas',
		]);

		$app['session']->set('logins', $app['s_logins']);
		$app['session']->set('schema', $app['pp_schema']);

		$referrer = $_SERVER['HTTP_REFERER'] ?? 'unknown';

		if ($referrer !== 'unknown')
		{
			// record logins to link the apikeys to domains and systems
			$domain_referrer = strtolower(parse_url($referrer, PHP_URL_HOST));
			$app['xdb']->set('apikey_login', $apikey, [
				'domain' => $domain_referrer
			], $app['pp_schema']);
		}

		$app['monolog']->info('eLAS guest login using token ' .
			$token . ' succeeded. referrer: ' . $referrer,
			['schema' => $app['pp_schema']]);

		$glue = strpos($location, '?') === false ? '?' : '&';

		header('Location: ' . $app->path($location, [
			'welcome'	=> '1',
			'role'		=> 'g',
			'system'	=> $app['pp_system'],
		]));
		exit;
	}
	else
	{
		$app['alert']->error('De interSysteem login is mislukt.');
	}
}
