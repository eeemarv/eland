<?php

if (php_sapi_name() !== 'cli')
{
	echo '-- cli only --';
	exit;
}

require_once __DIR__ . '/includes/worker.php';

echo "worker start\n";

$queue_task_interval_ary = [
	'mail'				=> 5,
	'autominlimit'		=> 1,
	'geocode'			=> 120,
];

$queue_task_next_ary = [];
$now = time();

foreach ($queue_task_interval_ary as $task => $interval)
{
	$queue_task_next_ary[$task] = $now + $interval;
}

$loop_count = 1;

while (true)
{
	$app['eland.log_db']->update();

	sleep(1);

	$omit_queue_task_ary = [];

	$now = time();

	foreach ($queue_task_next_ary as $queue_task => $time)
	{
		if ($time > $now)
		{
			$omit_queue_task_ary[] = $queue_task;
		}
	}

	unset($task);

	error_log(implode(' ! ', $omit_queue_task_ary));

	$queue_task = $app['eland.queue']->get($omit_queue_task_ary);

	if ($queue_task)
	{
		$topic = $queue_task['topic'];
		$data = $queue_task['data'];

		if (!isset($data['schema']))
		{
			error_log('no schema set for queue msg id : ' . $queue_task['id'] . ' data: ' .
				json_encode($data) . ' topic: ' . $topic);
		}
		else if (!isset($queue_task_interval_ary[$topic]))
		{
			error_log('Queue task not recognised: ' . json_encode($queue_task));
		}
		else
		{
			$app['eland.task.' . $topic]->process($data);

			$queue_task_next_ary[$topic] = $now + $queue_task_interval_ary[$topic];
		}
	}

	sleep(1);

	if ($loop_count % 10 == 0)
	{
		if ($app['eland.cron_schedule']->find_next())
		{
			$name = $app['eland.cron_schedule']->get_name();
			$schema = $app['eland.cron_schedule']->get_schema();
			echo 'cron task: ' . $schema . ' . ' . $name . "\n";
			$app['eland.task.' . $name]->run($schema);
			$app['eland.cron_schedule']->update();
		}
		else
		{
			echo "no task to run\n";
		}
	}

//	sleep(1);

	error_log('... worker ... ' . $loop_count);
	$loop_count++;
}
