<?php

use Symfony\Component\Finder\Finder;
use util\task_container;

if (php_sapi_name() !== 'cli')
{
	echo '-- cli only --';
	exit;
}

$rootpath = '../';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../include/default.php';

$boot = $app['cache']->get('boot');

if (!count($boot))
{
	$boot = ['count' => 0];
}

$boot['count']++;
$app['cache']->set('boot', $boot);

echo 'worker started .. ' . $boot['count'] . "\n";

error_log('overall domain: ' . getenv('OVERALL_DOMAIN'));
error_log('schemas: ' . json_encode($app['groups']->get_schemas()));
error_log('hosts: ' . json_encode($app['groups']->get_hosts()));

$schema_task = new task_container($app, 'schema_task');

$loop_count = 1;

while (true)
{
	$monitor = $app['predis']->get('monitor_service_worker');

	if (isset($monitor) && $monitor !== '1')
	{
		$monitor = json_decode($monitor, true);
	}
	else
	{
		$monitor = [];
	}

	$now = time();
	$monitor[$boot['count']] = $now;

	if (max(array_keys($monitor)) !== $boot['count'])
	{
		$app['predis']->set('monitor_service_worker', json_encode($monitor));
		error_log('..worker..asleep.. ' . $boot['count']);
		sleep(300);
		continue;
	}

	sleep(60);

	if ($schema_task->should_run())
	{
		$schema_task->run();
	}

	if ($loop_count % 1000 === 0)
	{
		error_log('..process/worker.. ' . $boot['count'] . ' .. ' . $loop_count);
	}

	$day_ago = $now - 86400;

	foreach ($monitor as $worker => $time)
	{
		if ($time < $day_ago)
		{
			unset($monitor[$worker]);
		}
	}

	$app['predis']->set('monitor_service_worker', json_encode($monitor));
	$app['predis']->expire('monitor_service_worker', 900);

	$loop_count++;
}
