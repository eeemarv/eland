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
		from ' . $app['tschema'] . '.parameters
		where parameter = \'schemaversion\'');

	if ($currentversion >= $schemaversion)
	{
		error_log('-- Database already up to date -- ');
	}
	else
	{
		error_log(' -- eLAS/eLAND database needs to
			upgrade from ' . $currentversion .
			' to ' . $schemaversion . ' -- ');

		while($currentversion < $schemaversion)
		{
			$currentversion++;

			$app['elas_db_upgrade']->run($currentversion, $app['tschema']);
		}

		$m = 'Upgraded database from schema version ' .
			$dbversion . ' to ' . $currentversion;

		error_log(' -- ' . $m . ' -- ');
		$app['monolog']->info('DB: ' . $m, ['schema' => $app['tschema']]);
	}

	header('Location: ' . $app['rootpath'] . 'init.php?step=2');
	exit;
}
else if ($step == 2)
{
	$found = false;

	$rs = $app['db']->prepare('select id, "PictureFile"
		from ' . $app['tschema'] . '.users
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

			if ($app['s3']->exists($filename_bucket))
			{
				$found = true;
				break;
			}
		}

		if (!$found)
		{
			$app['db']->update($app['tschema'] . '.users',
				['"PictureFile"' => null], ['id' => $user_id]);

			error_log(' -- Profile image not present,
				deleted in database: ' . $filename . ' -- ');

			$app['monolog']->info('cron: Profile image file of user ' .
				$user_id . ' was not found in bucket: deleted
				from database. Deleted filename : ' .
				$filename, ['schema' => $app['tschema']]);
		}
		else if ($f_schema !== $app['tschema'])
		{
			$new_filename = $app['tschema'] . '_u_' . $user_id .
				'_' . sha1(time() . $filename) . '.jpg';

			$err = $app['s3']->copy($filename_bucket, $new_filename);

			if ($err)
			{
				error_log(' -- error: ' . $err . ' -- ');

				$app['monolog']->info('init: copy img error: ' .
					$err, ['schema' => $app['tschema']]);

				continue;
			}

			$app['db']->update($app['tschema'] . '.users',
				['"PictureFile"' => $new_filename],
				['id' => $user_id]);

			error_log(' -- Profile image renamed, old: ' .
				$filename . ' new: ' . $new_filename . ' -- ');

			$app['monolog']->info('init: Profile image file renamed, Old: ' .
				$filename . ' New: ' . $new_filename,
				['schema' => $app['tschema']]);
		}
	}

	if ($found)
	{
		error_log(' found img ');
		$start += 50;
		header('Location: ' . $app['rootpath'] . 'init.php?step=2&start=' . $start);
		exit;
	}

	header('Location: ' . $app['rootpath'] . 'init.php?step=3');
	exit;
}
else if ($step == 3)
{

	$message_images = $app['db']->fetchAll('select id, msgid, "PictureFile"
		from ' . $app['tschema'] . '.msgpictures
		order by id asc
		limit 50 offset ' . $start);

	if (!count($message_images))
	{
		error_log(' to step 4 ');
		header('Location: ' . $app['rootpath'] . 'init.php?step=4');
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

			if ($app['s3']->exists($filename_bucket))
			{
				$found = true;
				break;
			}
		}

		if (!$found)
		{
			$app['db']->delete($app['tschema'] . '.msgpictures',
				['id' => $id]);

			error_log(' -- Message image not present,
				deleted in database: ' . $filename . ' -- ');

			$app['monolog']->info('init: Image file of message ' . $msg_id .
				' not found in bucket: deleted from database. Deleted : ' .
				$filename . ' id: ' . $id, ['schema' => $app['tschema']]);
		}
		else if ($f_schema !== $app['tschema'])
		{
			$new_filename = $app['tschema'] . '_m_' .
				$msg_id . '_' . sha1(time() .
				$filename) . '.jpg';

			$err = $app['s3']->copy($filename_bucket, $new_filename);

			if ($err)
			{
				error_log(' -- error: ' . $err . ' -- ');

				$app['monolog']->info('init: copy img error: ' . $err,
					['schema' => $app['tschema']]);
				continue;
			}

			$app['db']->update($app['tschema'] . '.msgpictures',
				['"PictureFile"' => $new_filename], ['id' => $id]);

			error_log('Profile image renamed, old: ' .
				$filename . ' new: ' . $new_filename);

			$app['monolog']->info('init: Message image file renamed, Old : ' .
				$filename . ' New: ' . $new_filename, ['schema' => $app['tschema']]);

		}
	}

	error_log('Sync image files next.');

	$start += 50;

	header('Location: ' . $app['rootpath'] . 'init.php?step=3&start=' . $start);
	exit;
}
else if ($step == 4)
{

	error_log('*** clear users cache ***');

	$users = $app['db']->fetchAll('select id
		from ' . $app['tschema'] . '.users');

	foreach ($users as $u)
	{
		$app['predis']->del($app['tschema'] . '_user_' . $u['id']);
	}

	header('Location: ' . $app['rootpath'] . 'init.php?step=5');
	exit;
}
else if ($step == 5)
{
	$app['db']->executeQuery('delete from ' .
		$app['tschema'] . '.tokens');

	error_log('*** empty tokens table (is not used anymore) *** ');

	header('Location: ' . $app['rootpath'] . 'init.php?step=6');
	exit;
}
else if ($step == 6)
{
	$app['db']->executeQuery('delete from ' .
		$app['tschema'] . '.city_distance');

	error_log('*** empty city_distance table (is not used anymore) *** ');

	header('Location: ' . $app['rootpath'] . 'init.php?step=7');
	exit;
}
else if ($step == 7)
{
	error_log('*** Queue for Geocoding, start: ' . $start . ' ***');

	$rs = $app['db']->prepare('select c.id_user, c.value
		from ' . $app['tschema'] . '.contact c, ' .
			$app['tschema'] . '.type_contact tc
		where c.id_type_contact = tc.id
			and tc.abbrev = \'adr\'
		order by c.id_user asc
		limit 50 offset ' . $start);

	$rs->execute();

	$more_geocoding = false;

	while ($row = $rs->fetch())
	{
		$app['queue.geocode']->cond_queue([
			'adr'		=> $row['value'],
			'uid'		=> $row['id_user'],
			'schema'	=> $app['tschema'],
		]);

		$more_geocoding = true;
	}

	if ($more_geocoding)
	{
		$start += 20;
		header('Location: ' . $app['rootpath'] . 'init.php?step=7&start=' . $start);
		exit;
	}

	error_log('*** init finished ***');
	exit;
}