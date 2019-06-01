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

	$record = $app['queue']->get(['geocode']);

	if (count($record))
	{
		$app['queue.geocode']->process($record['data']);
	}

	$app['monitor_process']->periodic_log();
}
