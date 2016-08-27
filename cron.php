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

/*
if ($app['redis']->get('process_queue_sleep') && $app['eland.queue']->count())
{
	$queue = $app['eland.queue']->get('', 5);

	foreach ($queue as $q_msg)
	{
		$topic = $q_msg['topic'];
		$data = $q_msg['data'];

		if (!isset($data['schema']))
		{
			$app['monolog']->error('no schema set for queue msg id : ' . $q_msg['id'] . ' data: ' .
				json_encode($data) . ' topic: ' . $topic);

			continue;
		}

		switch ($q_msg['topic'])
		{
			case 'mail':
				
				break;
			case 'autominlimit':

				break;
			default:
				$app['monolog']->error('Task not recognised: ' . json_encode($q_msg));
				break;
		}
	}

	$app['redis']->set('process_queue_sleep', '1');
	$app['redis']->expire('process_queue_sleep', 50);
	echo '-- Cron end. --';
	exit;	
}



*/

/*
 *  select in which schema to perform updates
 */

$schema_lastrun_ary = [];

foreach ($app['eland.groups']->get_schemas() as $ho => $sch)
{
	$lastrun = $app['db']->fetchColumn('select max(lastrun) from ' . $sch . '.cron');
	$schema_lastrun_ary[$sch] = ($lastrun) ?: 0;
}

unset($sch, $ho, $selected);

if ($app['eland.groups']->count())
{
	asort($schema_lastrun_ary);

	echo 'Schema (domain): last cron timestamp : interletsqueue timestamp' . $r;
	echo '---------------------------------------------------------------' . $r;

	foreach ($schema_lastrun_ary as $sch => $time)
	{
		echo $sch . ' (' . $app['eland.groups']->get_host($sch) . '): ' . $time;

		if (!isset($selected))
		{
			$app['eland.this_group']->force($sch);
			echo ' (selected)';
			$selected = true;
		}

		echo $r;
	}
}
else
{
	echo '-- No installed domains found. --' . $r;
	exit;
}

echo '*** Cron system running [' . $app['eland.this_group']->get_schema() . '] ***' . $r;

$app['eland.base_url'] = $app['eland.protocol'] . $app['eland.this_group']->get_host();


/**
 * typeahead && msgs from eLAS interlets update
 */

$app['eland.task.interlets_fetch']->run();

/*
 * Process autominlimits
 */

$autominlimit_queue = $app['eland.queue']->get('autominlimit', 6);

if (count($autominlimit_queue))
{
	echo '-- processing autominlimit queue -- ' . $r;

	foreach ($autominlimit_queue as $q)
	{
		$app['eland.task.autominlimit']->process($q['data']);
	}

	echo '--- end queue autominlimit --- ' . $r;
}
else
{
	echo '-- autominlimit queue is empty --' . $r;
}

/** send mail **/

$mail_queue = $app['eland.queue']->get('mail', 6);

if (count($mail_queue))
{
	echo '-- processing mail queue -- ' . $r;

	foreach ($mail_queue as $q)
	{
		$app['eland.task.mail']->process($q['data']);
	}

	echo '--- end mail queue --- ' . $r;
}
else
{
	echo '-- mail queue is empty --' . $r;
}


$geo_queue = $app['eland.queue']->get('geocode', 6);

if (count($geo_queue))
{
	echo '-- processing geo queue -- ' . $r;

	foreach ($geo_queue as $q)
	{
		$app['eland.task.geocode']->process($q['data']);
	}

	echo '-- end geo queue -- ' . $r;
}
else
{
	echo '-- geo queue is empty -- ' . $r;
}


run_cronjob('geocode', 7200);

function geocode()
{
	global $app;

	$app['eland.task.geocode']->run();

	return true;
}


/**
 * Periodic overview mail
 */

run_cronjob('saldo', 86400 * readconfigfromdb('saldofreqdays'));

function saldo()
{
	global $app;

	$app['eland.task.saldo']->run();

	return true;
}

/**
 * Report expired messages to the admin by mail
 */

run_cronjob('admin_exp_msg', 86400 * readconfigfromdb('adminmsgexpfreqdays'), readconfigfromdb('adminmsgexp'));

function admin_exp_msg()
{
	global $app, $now, $r;

	$app['eland.task.admin_exp_msg']->run();

	return true;
}

/**
 * Notify users of expired messages
 */

run_cronjob('user_exp_msgs', 86400, readconfigfromdb('msgexpwarnenabled'));

function user_exp_msgs()
{
	global $app;

	$app['eland.task.user_exp_msgs']->run();

	return true;
}

/**
 * Cleanup messages
 */

run_cronjob('cleanup_messages', 86400);

function cleanup_messages()
{
	global $app;

	$app['eland.task.cleanup_messages']->run();

	return true;
}

/**
 * Resyncronize balances
 */

run_cronjob('saldo_update', 86400); 

function saldo_update()
{
	global $app;

	$app['eland.task.saldo_update']->run();

	return true;
}

/**
 * remove expired newsitems
 */

run_cronjob('cleanup_news', 86400);

function cleanup_news()
{
	global $app;

	$app['eland.task.cleanup_news']->run();

	return true;
}


/**
 * cleanup image files // schema-independent cronjob.
 * images are deleted from s3 1 year after been deleted from db.
 */

$app['eland.task.cleanup_image_files']->run();


/**
 * Cleanup old logs
 */

run_cronjob('cleanup_logs', 86400);

function cleanup_logs()
{
	global $app;

	$app['eland.task.cleanup_logs']->run();

	return true;
}

/**
 * Dummy cronjob in order to keep rotating groups for cronjobs.
 */

run_cronjob('cronschedule', 10);

function cronschedule()
{
	return true;
}

echo "*** Cron run finished ***" . $r;
exit;

////////////////////

function run_cronjob($name, $interval = 300, $enabled = null)
{
	global $app, $r, $now;
	static $lastrun_ary;

	if (!(isset($lastrun_ary) && is_array($lastrun_ary)))
	{
		$lastrun_ary = [];

		$rs = $app['db']->prepare('select cronjob, lastrun from cron');

		$rs->execute();

		while ($row = $rs->fetch())
		{
			$lastrun_ary[$row['cronjob']] = $row['lastrun'];
		}
	}

	$time = time();
	$lastrun = (isset($lastrun_ary[$name])) ? strtotime($lastrun_ary[$name] . ' UTC') : 0;

	if (!((($time - $interval) > $lastrun) & ($enabled || !isset($enabled))))
	{
		echo '+++ Cronjob: ' . $name . ' not running. +++' . $r;
		return;
	}

	echo '+++ Running ' . $name . ' +++' . $r;

	$updated = call_user_func($name);

	$lastrun = ((($time - ($lastrun + $interval)) > 86400) || ($interval < 86401)) ? $time : $lastrun + $interval;

	if (isset($lastrun_ary[$name]))
	{
		$app['db']->update('cron', ['lastrun' => gmdate('Y-m-d H:i:s', $lastrun)], ['cronjob' => $name]);
	}
	else
	{
		$app['db']->insert('cron', ['cronjob' => $name, 'lastrun'	=> $now]);
	}

	echo '+++ Cronjob ' . $name . ' finished. +++' . $r;

	return $updated;
}
