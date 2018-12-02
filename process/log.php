<?php

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

if (!isset($boot['log']))
{
	$boot['log'] = $boot['count'];
}

$boot['log']++;
$app['cache']->set('boot', $boot);

error_log('process/log started .. ' . $boot['log']);

$loop_count = 1;

while (true)
{
/*
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
	$monitor[$boot['log']] = $now;

	if (max(array_keys($monitor)) !== $boot['count'])
	{
		$app['predis']->set('monitor_service_worker', json_encode($monitor));
		error_log('..worker..asleep.. ' . $boot['count']);
		sleep(300);
		continue;
	}
*/
	$app['log_db']->update();

	sleep(5);

	if ($loop_count % 10000 === 0)
	{
		error_log('..process/log.. ' . $boot['log'] . ' .. ' . $loop_count);
	}
/*
	if ($loop_count % 10 === 0)
	{
		$half_hour_ago = $now - 1800;

		foreach ($monitor as $worker => $time)
		{
			if ($time < $half_hour_ago)
			{
				unset($monitor['worker']);
			}
		}

		$app['predis']->set('monitor_service_worker', json_encode($monitor));
		$app['predis']->expire('monitor_service_worker', 900);
	}
*/

	$loop_count++;
}
