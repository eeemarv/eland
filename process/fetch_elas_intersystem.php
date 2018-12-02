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

if (!isset($boot['fetch_elas_intersystem']))
{
	$boot['fetch_elas_intersystem'] = $boot['count'];
}

$boot['fetch_elas_intersystem']++;
$app['cache']->set('boot', $boot);

error_log('process/fetch_elas_intersystem started .. ' .
	$boot['fetch_elas_intersystem']);

$loop_count = 1;

while (true)
{
	sleep(450);

	$app['task.get_elas_intersystem_domains']->process();

	sleep(450);

	$app['task.fetch_elas_intersystem']->process();

	if ($loop_count % 100 === 0)
	{
		error_log('..process/fetch_elas_intersystem.. ' .
			$boot['fetch_elas_intersystem'] .
			' .. ' .
			$loop_count);
	}

	$loop_count++;
}
