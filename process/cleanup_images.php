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

if (!isset($boot['cleanup_images']))
{
	$boot['cleanup_images'] = $boot['count'];
}

$boot['cleanup_images']++;
$app['cache']->set('boot', $boot);

error_log('process/cleanup_images started .. ' . $boot['cleanup_images']);

$loop_count = 1;

while (true)
{
	sleep(900);

	$app['task.cleanup_images']->process();

	if ($loop_count % 100 === 0)
	{
		error_log('..process/cleanup_images.. ' .
			$boot['cleanup_images'] .
			' .. ' .
			$loop_count);
	}

	$loop_count++;
}
