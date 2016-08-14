<?php

$r = "\r\n";

$step = $_GET['step']) ?? 1;

$php_sapi_name = php_sapi_name();

if ($php_sapi_name == 'cli')
{
	echo 'The init should not run from the cli but from the http web server.' . $r;
	exit;
}

defined('__DIR__') or define('__DIR__', dirname(__FILE__));
chdir(__DIR__);

$rootpath = './';
$page_access = 'anonymous';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_upgrade.php';

header('Content-Type:text/plain');
echo '*** Init eLAND ***' . $r;
echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r;
echo "schema: " . $schema . ' systemtag: ' . readconfigfromdb('systemtag') . $r;

if ($step == 2 || $step == 3)
{
	echo 'Sync the image files.' . $r;

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
		echo '-- Database already up to date -- ' . $r;
	}
	else
	{
		echo "eLAS/eLAND database needs to upgrade from $currentversion to $schemaversion\n";

		while($currentversion < $schemaversion)
		{
			$currentversion++;

			if(doupgrade($currentversion))
			{
				$doneversion = $currentversion;
			}
		}

		$m = 'Upgraded database from schema version ' . $dbversion . ' to ' . $currentversion;
		echo $m . "\n";
		$app['monolog']->info('DB: ' . $m);
	}

	header('Location: ' . $rootpath . 'init.php?step=2');
	exit;
}
else if ($step == 2)
{
	$rs = $app['db']->prepare('SELECT id, "PictureFile" FROM users WHERE "PictureFile" IS NOT NULL');

	$rs->execute();

	while($row = $rs->fetch())
	{
		$filename = $row['PictureFile'];
		$user_id = $row['id'];

		list($f_schema) = explode('_', $filename);

		$filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);

		$found = false;

		foreach ($possible_extensions as $extension)
		{
			$filename_bucket = $filename_no_ext . '.' . $extension;

			if ($app['eland.s3']->img_exists($filename_bucket))
			{
				$found = true;
				break;
			}
		}

		if (!$found)
		{
			$app['db']->update('users', ['"PictureFile"' => null], ['id' => $user_id]);
			echo 'Profile image not present, deleted in database: ' . $filename . $r;
			$app['monolog']->info('cron: Profile image file of user ' . $user_id . ' was not found in bucket: deleted from database. Deleted filename : ' . $filename);
		}
		else if ($f_schema != $schema)
		{
			$new_filename = $schema . '_u_' . $user_id . '_' . sha1(time() . $filename) . '.jpg';

			$err = $app['eland.s3']->img_copy($filename_bucket, $new_filename);

			if ($err)
			{
				echo 'error: ' . $err . $r . $r;
				$app['monolog']->info('init: copy img error: ' . $err);
				continue;
			}

			$app['db']->update('users', ['"PictureFile"' => $new_filename], ['id' => $user_id]);
			echo 'Profile image renamed, old: ' . $filename . ' new: ' . $new_filename . $r;
			$app['monolog']->info('init: Profile image file renamed, Old: ' . $filename . ' New: ' . $new_filename);
		}
	}

	header('Location: ' . $rootpath . 'init.php?step=3');
	exit;
}
else if ($step == 3)
{

	$message_images = $app['db']->fetchAll('SELECT id, msgid, "PictureFile" FROM msgpictures');

	foreach ($message_images as $image)
	{
		$filename = $image['PictureFile'];
		$msg_id = $image['msgid'];
		$id = $image['id'];

		list($f_schema) = explode('_', $filename);

		$filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);

		$found = false;

		foreach ($possible_extensions as $extension)
		{
			$filename_bucket = $filename_no_ext . '.' . $extension;

			if ($app['eland.s3']->img_exists($filename_bucket))
			{
				$found = true;
				break;
			}
		}

		if (!$found)
		{
			$app['db']->delete('msgpictures', ['id' => $id]);
			echo 'Message image not present, deleted in database: ' . $filename . $r;
			$app['monolog']->info('init: Image file of message ' . $msg_id . ' not found in bucket: deleted from database. Deleted : ' . $filename . ' id: ' . $id);
		}
		else if ($f_schema != $schema)
		{
			$new_filename = $schema . '_m_' . $msg_id . '_' . sha1(time() . $filename) . '.jpg';

			$err = $app['eland.s3']->img_copy($filename_bucket, $new_filename);

			if ($err)
			{
				echo 'error: ' . $err . $r . $r;
				$app['monolog']->info('init: copy img error: ' . $err);
				continue;
			}

			$app['db']->update('msgpictures', ['"PictureFile"' => $new_filename], ['id' => $id]);
			echo 'Profile image renamed, old: ' . $filename . ' new: ' . $new_filename . $r;
			$app['monolog']->info('init: Message image file renamed, Old : ' . $filename . ' New: ' . $new_filename);

		}
	}

	echo 'Sync image files ready.' . $r;

	header('Location: ' . $rootpath . 'init.php?step=4');
	exit;
}

/*
echo 'Cleanup orphaned contacts. ' . $r;

$orphaned_contacts = [];

$rs = $app['db']->prepare('select c.id, c.value
	from contact c
	left join users u
		on c.id_user = u.id
	where u.id IS NULL');

$rs->execute();

while($row = $rs->fetch())
{
	$orphaned_contacts[$row['id']] = $row['value'];
}

$count = count($orphaned_contacts);

if ($count)
{
	$app['db']->executeQuery('delete * from contact where id IN (?)',
		[implode(', ', array_keys($orphaned_contacts))],
		[\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
	);

	echo 'Found & deleted ' . $count . ' orphaned contacts.' . $r;
	echo '---------------------------------------------' . $r;
	foreach ($orphaned_contacts as $id => $val)
	{
		echo $id . ' => ' . $val . $r;
	}
}
else
{
	echo 'none found.' . $r;
}
*/

else if ($step == 4)
{

	echo '*** clear users cache ***';

	$users = $app['db']->fetchAll('select id from users');

	foreach ($users as $u)
	{
		$app['redis']->del($schema . '_user_' . $u['id']);
	}

	echo "\n";

	echo '** end **';
}
