<?php

if (php_sapi_name() !== 'cli')
{
	echo '-- cli only --';
	exit;
}

require_once __DIR__ . '/../include/process.php';

while (true)
{
	if (!$app['monitor_process']->wait_most_recent())
	{
		continue;
	}

	// $schema is not used, logs from all schemas are cleaned up.

	$treshold = gmdate('Y-m-d H:i:s', time() - 86400 * 30);

	$app['db']->executeQuery('delete from xdb.logs
		where ts < ?', [$treshold]);

	$app['monitor_process']->periodic_log();
}
