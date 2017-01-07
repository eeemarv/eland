<?php

use Symfony\Component\Finder\Finder;
use eland\util\task_container;

if (php_sapi_name() !== 'cli')
{
	echo '-- cli only --';
	exit;
}

require_once __DIR__ . '/include/worker.php';

echo "worker started\n";

$queue = new task_container($app, 'queue');
$task = new task_container($app, 'task');
$schema_task = new task_container($app, 'schema_task');


$loop_count = 1;

while (true)
{
	$app['eland.log_db']->update();

	sleep(1);

	if ($task->should_run())
	{
		$task->run();
	}
	else if ($schema_task->should_run())
	{
		$schema_task->run();
	}

	if ($loop_count % 60 == 0)
	{
		error_log('...worker... ' . $loop_count);
	}

	$loop_count++;
}



/*
$now = time();

// get queue tasks 

$queue_task_next_ary = $queue_task_interval_ary = [];

$finder = new Finder();
$finder->files()
	->in(__DIR__ . '/queue')
	->name('*.php');

foreach ($finder as $file)
{
    $path = $file->getRelativePathname();

    $queue_task = basename($path, '.php');

    $queue_task_interval_ary[$queue_task] = $app['eland.queue.' . $queue_task]->get_interval();
}

error_log('-- queue tasks: ');
var_dump($queue_task_interval_ary);

foreach ($queue_task_interval_ary as $task => $interval)
{
	$queue_task_next_ary[$task] = $now + $interval;
}

// get tasks

$task_next_ary = $task_interval_ary = [];

$finder = new Finder();
$finder->files()
	->in(__DIR__ . '/task')
	->name('*.php');

foreach ($finder as $file)
{
    $path = $file->getRelativePathname();

    $queue_task = basename($path, '.php');

    $task_interval_ary[$queue_task] = $app['eland.task.' . $queue_task]->get_interval();
}

error_log('-- tasks: ');
var_dump($task_interval_ary);

foreach ($task_interval_ary as $task => $interval)
{
	$task_next_ary[$task] = $now + $interval;
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

	unset($queue_task);

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
			$app['eland.queue.' . $topic]->process($data);

			$queue_task_next_ary[$topic] = $now + $queue_task_interval_ary[$topic];
		}
	}
	else if ($app['eland.task_schedule']->find_next())
	{
		$name = $app['eland.task_schedule']->get_name();
		$schema = $app['eland.task_schedule']->get_schema();
		echo 'cron task: ' . $schema . ' . ' . $name . "\n";
		$app['eland.task.' . $name]->set_schema($schema);
		$app['eland.task.' . $name]->run();
		$app['eland.task_schedule']->update();
	}


	if ($loop_count % 60 == 0)
	{
		error_log('...worker... ' . $loop_count);
	}

	$loop_count++;
}
*/
