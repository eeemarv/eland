<?php

$step = $_GET['step'] ?? 1;
$start = $_GET['start'] ?? 0;

set_time_limit(300);

if (!ctype_digit((string) $start))
{
	exit;
}

if (!ctype_digit((string) $step))
{
	exit;
}

$php_sapi_name = php_sapi_name();

if ($php_sapi_name == 'cli')
{
	error_log('The init should not run from the cli but from the http web server.');
	exit;
}

defined('__DIR__') or define('__DIR__', dirname(__FILE__));
chdir(__DIR__);

$page_access = 'anonymous';
require_once __DIR__ . '/include/web.php';

if ($step == 2 || $step == 3)
{
	error_log(' -- Sync the image files. --');

	$possible_extensions = ['jpg', 'jpeg', 'JPG', 'JPEG'];
}

// Upgrade the DB first if required

if ($step == 1)
{
	$schemaversion = 31000;

	$currentversion = $dbversion = $app['db']->fetchColumn('select value
		from parameters
		where parameter = \'schemaversion\'');

	if ($currentversion >= $schemaversion)
	{
		error_log('-- Database already up to date -- ');
	}
	else
	{
		error_log(' -- eLAS/eLAND database needs to upgrade from ' . $currentversion . ' to ' . $schemaversion . ' -- ');

		while($currentversion < $schemaversion)
		{
			$currentversion++;

			if($app['elas_db_upgrade']->run($currentversion))
			{
				$doneversion = $currentversion;
			}
		}

		$m = 'Upgraded database from schema version ' . $dbversion . ' to ' . $currentversion;
		error_log(' -- ' . $m . ' -- ');
		$app['monolog']->info('DB: ' . $m);
	}

	header('Location: ' . $rootpath . 'init.php?step=2');
	exit;
}
else if ($step == 2)
{
	$found = false;

	$rs = $app['db']->prepare('select id, "PictureFile"
		from users
		where "PictureFile" is not null
		order by id asc
		limit 50 offset ' . $start);

	$rs->execute();

	while($row = $rs->fetch())
	{
		$found = true;

		$filename = $row['PictureFile'];
		$user_id = $row['id'];

		[$f_schema] = explode('_', $filename);

		$filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);

		$found = false;

		foreach ($possible_extensions as $extension)
		{
			$filename_bucket = $filename_no_ext . '.' . $extension;

			if ($app['s3']->img_exists($filename_bucket))
			{
				$found = true;
				break;
			}
		}

		if (!$found)
		{
			$app['db']->update('users', ['"PictureFile"' => null], ['id' => $user_id]);
			error_log(' -- Profile image not present, deleted in database: ' . $filename . ' -- ');
			$app['monolog']->info('cron: Profile image file of user ' . $user_id . ' was not found in bucket: deleted from database. Deleted filename : ' . $filename);
		}
		else if ($f_schema != $app['this_group']->get_schema())
		{
			$new_filename = $app['this_group']->get_schema() . '_u_' . $user_id . '_' . sha1(time() . $filename) . '.jpg';

			$err = $app['s3']->img_copy($filename_bucket, $new_filename);

			if ($err)
			{
				error_log(' -- error: ' . $err . ' -- ');
				$app['monolog']->info('init: copy img error: ' . $err);
				continue;
			}

			$app['db']->update('users', ['"PictureFile"' => $new_filename], ['id' => $user_id]);
			error_log(' -- Profile image renamed, old: ' . $filename . ' new: ' . $new_filename . ' -- ');
			$app['monolog']->info('init: Profile image file renamed, Old: ' . $filename . ' New: ' . $new_filename);
		}
	}

	if ($found)
	{
		error_log(' found img ');
		$start += 50;
		header('Location: ' . $rootpath . 'init.php?step=2&start=' . $start);
		exit;
	}

	header('Location: ' . $rootpath . 'init.php?step=3');
	exit;
}
else if ($step == 3)
{

	$message_images = $app['db']->fetchAll('select id, msgid, "PictureFile"
		from msgpictures
		order by id asc
		limit 50 offset ' . $start);

	if (!count($message_images))
	{
		error_log(' to step 4 ');
		header('Location: ' . $rootpath . 'init.php?step=4');
		exit;
	}

	foreach ($message_images as $image)
	{
		$filename = $image['PictureFile'];
		$msg_id = $image['msgid'];
		$id = $image['id'];

		[$f_schema] = explode('_', $filename);

		$filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);

		$found = false;

		foreach ($possible_extensions as $extension)
		{
			$filename_bucket = $filename_no_ext . '.' . $extension;

			if ($app['s3']->img_exists($filename_bucket))
			{
				$found = true;
				break;
			}
		}

		if (!$found)
		{
			$app['db']->delete('msgpictures', ['id' => $id]);
			error_log(' -- Message image not present, deleted in database: ' . $filename . ' -- ');
			$app['monolog']->info('init: Image file of message ' . $msg_id . ' not found in bucket: deleted from database. Deleted : ' . $filename . ' id: ' . $id);
		}
		else if ($f_schema != $app['this_group']->get_schema())
		{
			$new_filename = $app['this_group']->get_schema() . '_m_' . $msg_id . '_' . sha1(time() . $filename) . '.jpg';

			$err = $app['s3']->img_copy($filename_bucket, $new_filename);

			if ($err)
			{
				error_log(' -- error: ' . $err . ' -- ');
				$app['monolog']->info('init: copy img error: ' . $err);
				continue;
			}

			$app['db']->update('msgpictures', ['"PictureFile"' => $new_filename], ['id' => $id]);
			error_log('Profile image renamed, old: ' . $filename . ' new: ' . $new_filename);
			$app['monolog']->info('init: Message image file renamed, Old : ' . $filename . ' New: ' . $new_filename);

		}
	}

	error_log('Sync image files next.');

	$start += 50;

	header('Location: ' . $rootpath . 'init.php?step=3&start=' . $start);
	exit;
}
else if ($step == 4)
{

	error_log('*** clear users cache ***');

	$users = $app['db']->fetchAll('select id from users');

	foreach ($users as $u)
	{
		$app['predis']->del($app['this_group']->get_schema() . '_user_' . $u['id']);
	}

	header('Location: ' . $rootpath . 'init.php?step=5');
	exit;
}
else if ($step == 5)
{
	$app['db']->executeQuery('delete from tokens');

	error_log('*** empty tokens table (is not used anymore) *** ');

	header('Location: ' . $rootpath . 'init.php?step=6');
	exit;
}
else if ($step == 6)
{
	$app['db']->executeQuery('delete from city_distance');

	error_log('*** empty city_distance table (is not used anymore) *** ');

	error_log('** init end **');

	exit;
}
