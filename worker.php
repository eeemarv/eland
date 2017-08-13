<?php

use Symfony\Component\Finder\Finder;
use util\queue_container;
use util\task_container;

if (php_sapi_name() !== 'cli')
{
	echo '-- cli only --';
	exit;
}

require_once __DIR__ . '/include/default.php';

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

$queue = new queue_container($app, 'queue');
$task = new task_container($app, 'task');
$schema_task = new task_container($app, 'schema_task');

$loop_count = 1;

$app['predis']->set('block_task', '1');
$app['predis']->expire('block_task', 3);

while (true)
{

	$app['log_db']->update();

	sleep(1);

	if ($queue->should_run())
	{
		$queue->run();
	}
	else if ($task->should_run())
	{
		$task->run();
	}
	else if ($schema_task->should_run())
	{
		$schema_task->run();
	}

	if ($loop_count % 1000 === 0)
	{
		error_log('..worker.. ' . $boot['count'] . ' .. ' . $loop_count);
	}

	if ($loop_count % 10 === 0)
	{
		$app['predis']->set('monitor_service_worker', '1');
		$app['predis']->expire('monitor_service_worker', 900);
	}

	$loop_count++;
}

