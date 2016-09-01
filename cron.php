<?php

$r = "<br>\r\n";
$now = gmdate('Y-m-d H:i:s');

$php_sapi_name = php_sapi_name();

if ($php_sapi_name == 'cli')
{
	echo 'The cron should not run from the cli but from the http web server.' . $r;
	exit;
}

defined('__DIR__') or define('__DIR__', dirname(__FILE__));
chdir(__DIR__);

$page_access = 'anonymous';

require_once __DIR__ . '/includes/inc_default.php';

header('Content-Type:text/html');
echo '*** Cron eLAND ***' . $r;

echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r;

$app['eland.log_db']->update();

/** take cron task from the queue and process **/

if (!$app['redis']->get('process_queue_sleep') && ($count = $app['eland.queue']->count()))
{
	echo '-- process queue -- (count: ' . $count . ')' . $r;

	$queue_tasks = [
		'mail'			=> true,
		'autominlimit'	=> true,
		'geocode'		=> true,
	];

	$queue = $app['eland.queue']->get('', 10);

	foreach ($queue as $q_msg)
	{
		$topic = $q_msg['topic'];
		$data = $q_msg['data'];

		if (!isset($data['schema']))
		{
			error_log('no schema set for queue msg id : ' . $q_msg['id'] . ' data: ' .
				json_encode($data) . ' topic: ' . $topic);

			continue;
		}

		if (!isset($queue_tasks[$topic]))
		{
			error_log('Task not recognised: ' . json_encode($q_msg));
			continue;
		}

		$app['eland.task.' . $topic]->process($data);

	}

	$app['redis']->set('process_queue_sleep', '1');
	$app['redis']->expire('process_queue_sleep', 50);
	echo '-- Cron end. --';
	exit;
}

// $app['eland.this_group']->force('x');
// $app['eland.task.admin_exp_msg']->run('x');
//exit;

if ($app['eland.cron_schedule']->find_next())
{
	$name = $app['eland.cron_schedule']->get_name();
	$schema = $app['eland.cron_schedule']->get_schema();
	echo 'cron task: ' . $schema . ' . ' . $name . $r;
	$app['eland.task.' . $name]->run($schema);
	$app['eland.cron_schedule']->update();
}
else
{
	echo 'no task to run ' . $r;
}

echo ' -- Cron end. -- ' . $r;

