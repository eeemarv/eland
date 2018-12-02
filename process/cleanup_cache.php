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

if (!isset($boot['cleanup_cache']))
{
	$boot['cleanup_cache'] = $boot['count'];
}

$boot['cleanup_cache']++;
$app['cache']->set('boot', $boot);

error_log('process/cleanup_cache started .. ' . $boot['cleanup_cache']);

$loop_count = 1;

while (true)
{
	sleep(7200);

	$app['cache']->cleanup();
	error_log('process/cleanup_cache .. ' . $boot['cleanup_cache'] . ' .. ' . $loop_count);

	$loop_count++;
}
