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

if (!isset($boot['geocode']))
{
	$boot['geocode'] = $boot['count'];
}

$boot['geocode']++;
$app['cache']->set('boot', $boot);

error_log('process/geocode started .. ' . $boot['geocode']);

$loop_count = 1;

while (true)
{
	sleep(120);

	$record = $app['queue']->get(['geocode']);

	if (count($record))
	{
		$app['queue.geocode']->process($record['data']);
	}

	if ($loop_count % 5000 === 0)
	{
		error_log('..process/geocode.. ' . $boot['geocode'] . ' .. ' . $loop_count);
	}

	$loop_count++;
}
