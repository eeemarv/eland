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

if (!isset($boot['cleanup_logs']))
{
	$boot['cleanup_logs'] = $boot['count'];
}

$boot['cleanup_logs']++;
$app['cache']->set('boot', $boot);

error_log('process/cleanup_logs started .. ' . $boot['cleanup_logs']);

$loop_count = 1;

while (true)
{
	sleep(14400);

	// $schema is not used, logs from all schemas are cleaned up.

	$treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 30);

	$app['db']->executeQuery('delete from xdb.logs
		where ts < ?', [$treshold]);

	error_log('cleanup_logs .. ' . $boot['cleanup_logs'] . ' .. ' . $loop_count);

	$loop_count++;
}
