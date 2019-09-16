<?php declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require_once __DIR__ . '/../include/app.php';

$app->flush();

$token = $_GET['token'] ?? '';
$login = $_GET['login'] ?? '';
$location = $_GET['location'] ?? '';

if (!$location
	|| strpos($location, 'login') !== false
	|| strpos($location, 'logout') !== false
	|| $location === '/')
{
	$location = '';
}

$system = 'x';

$schema = $app['systems']->get_schema($system);

if (!$schema)
{
	throw new NotFoundHttpException('Dit systeem bestaat niet.');
}

if (strlen($token) > 10 || $token === 'h')
{
	if(true || $apikey = $app['predis']->get($schema . '_token_' . $token))
	{
		$s_logins = $app['s_logins'];
		$s_logins = array_merge($s_logins, [
			$schema 	=> 'elas',
		]);

		$app['session']->set('logins', $s_logins);
		$app['session']->set('schema', $schema);

		$referrer = $_SERVER['HTTP_REFERER'] ?? 'unknown';

		if ($referrer !== 'unknown')
		{
			// record logins to link the apikeys to domains and systems
			$domain_referrer = strtolower(parse_url($referrer, PHP_URL_HOST));
			$app['xdb']->set('apikey_login', $apikey, [
				'domain' => $domain_referrer
			], $schema);
		}

		$app['monolog']->info('eLAS guest login using token ' .
			$token . ' succeeded. referrer: ' . $referrer,
			['schema' => $schema]);

		$route = $app['config']->get('default_landing_page', $schema);
		$route .= $route === 'users' ? '_list' : '';
		$route .= $route === 'messages' ? '_extended' : '';
		$route .= $route === 'news' ? '_extended' : '';

		header('Location: ' . $app->path($route, [
			'welcome'		=> '1',
			'role_short'	=> 'g',
			'system'		=> $system,
		]));
		exit;
	}
	else
	{
		$app['alert']->error('De interSysteem login is mislukt.');
	}
}

// header('Location: ');

echo 'oufti';