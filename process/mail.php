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

if (!isset($boot['mail']))
{
	$boot['mail'] = $boot['count'];
}

$boot['mail']++;
$app['cache']->set('boot', $boot);

error_log('process/mail started .. ' . $boot['mail']);

$loop_count = 1;

while (true)
{
	sleep(5);

	$record = $app['queue']->get(['mail']);

	if (count($record))
	{
		$app['queue.mail']->process($record['data']);
	}

	if ($loop_count % 10000 === 0)
	{
		error_log('..process/mail.. ' . $boot['mail'] . ' .. ' . $loop_count);
	}

	$loop_count++;
}
