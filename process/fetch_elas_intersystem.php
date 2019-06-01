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

	$app['task.get_elas_intersystem_domains']->process();

	sleep(450);

	$app['task.fetch_elas_intersystem']->process();
	$app['monitor_process']->periodic_log();
}
