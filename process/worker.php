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

$app['monitor_process']->boot();

error_log('overall domain: ' . getenv('OVERALL_DOMAIN'));
error_log(' --- ');
error_log('schemas: ' . json_encode($app['systems']->get_schemas()));
error_log(' --- ');

$app['assets']->write_file_hash_ary();

error_log('+-----------------+');
error_log('| Worker Tasks    |');
error_log('+-----------------+');

$schema_task = new task_container($app, 'schema_task');

while (true)
{
	if (!$app['monitor_process']->wait_most_recent())
	{
		continue;
	}

	if ($schema_task->should_run())
	{
		$schema_task->run();
	}

	$app['monitor_process']->periodic_log();
}
