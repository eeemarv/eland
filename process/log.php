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

error_log('..process/log started .. ' . $boot['log']);

$loop_count = 1;

while (true)
{
	sleep(5);

	$app['log_db']->update();

	if ($loop_count % 10000 === 0)
	{
		error_log('..process/log.. ' . $boot['log'] . ' .. ' . $loop_count);
	}

	$loop_count++;
}
