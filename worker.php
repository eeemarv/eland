<?php

if (php_sapi_name() !== 'cli')
{
	echo '-- cli only --';
	exit;
}

require_once __DIR__ . '/includes/worker.php';

echo "worker start\n";

$task_interval_ary = [
	'mail'				=> 5,
	'autominlimit'		=> 1,
	'geocode'			=> 120,
];

$task_next_ary = [];
$now = time();

foreach ($task_interval_ary as $task => $interval)
{
	$task_next_ary[$task] = $now + $task_interval_ary[$task];
}

$loop_count = 1;

while (true)
{
	$app['eland.log_db']->update();

	sleep(1);

	$omit_task_ary = [];

	$now = time();

	foreach ($task_next_ary as $task => $time)
	{
		if ($time > $now)
		{
			$omit_task_ary[] = $task;
		}
	}

	unset($task);

	error_log(implode(' ! ', $omit_task_ary));

	$task = $app['eland.queue']->get($omit_task_ary);

	if ($task)
	{
		$topic = $task['topic'];
		$data = $task['data'];

		if (!isset($data['schema']))
		{
			error_log('no schema set for queue msg id : ' . $task['id'] . ' data: ' .
				json_encode($data) . ' topic: ' . $topic);
		}
		else if (!isset($task_interval_ary[$topic]))
		{
			error_log('Task not recognised: ' . json_encode($task));
		}
		else
		{
			$app['eland.task.' . $topic]->process($data);

			$task_next_ary[$topic] = $now + $task_interval_ary[$topic];
		}
	}

	sleep(1);






	sleep(1);

	error_log('... worker ... ' . $loop_count);
	$loop_count++;
}
