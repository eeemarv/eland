<?php

if (php_sapi_name() !== 'cli')
{
	echo '-- cli only --';
	exit;
}

require_once __DIR__ . '/includes/worker.php';

echo 'worker start';

$now = time();

$queue_tasks = [
	'mail'			=> ['next' => $now, 'interval' => 5],
	'autominlimit'	=> ['next' => $now, 'interval' => 1],
	'geocode'		=> ['next' => $now, 'interval' => 120],
];

$loop_count = 1;

while (true)
{
	$app['eland.log_db']->update();

	sleep(1);

	$omit_topics = [];

	$now = time();

	foreach ($queue_tasks as $task => $timing)
	{
		if ($timing['next'] < $now)
		{
			$omit_topics[] = $task;
		}
	}

	unset($task);

	$task = $app['eland.queue']->get($omit_topics);

	if ($task)
	{
		$topic = $task['topic'];
		$data = $task['data'];

		if (!isset($data['schema']))
		{
			error_log('no schema set for queue msg id : ' . $task['id'] . ' data: ' .
				json_encode($data) . ' topic: ' . $topic);
		}
		else if (!isset($queue_tasks[$topic]))
		{
			error_log('Task not recognised: ' . json_encode($q_msg));
		}
		else
		{
			$app['eland.task.' . $topic]->process($data);

			$queue_tasks[$topic]['next'] = $now + $queue_tasks[$topic]['interval'];
		}
	}

	sleep(1);





	error_log('... worker ... ' . $loop_count);

	sleep(1);

	$loop_count++;
}
