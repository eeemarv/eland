<?php

$r = "\r\n";

$php_sapi_name = php_sapi_name();

if ($php_sapi_name == 'cli')
{
	echo 'The init should not run from the cli but from the http web server.' . $r;
	exit;
}

defined('__DIR__') or define('__DIR__', dirname(__FILE__));
chdir(__DIR__);

$rootpath = './';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_upgrade.php';

header('Content-Type:text/plain');
echo '*** Init eLAS-Heroku ***' . $r;
echo 'php_sapi_name: ' . $php_sapi_name . $r;
echo 'php version: ' . phpversion() . $r;
echo "schema: " . $schema . ' systemtag: ' . readconfigfromdb('systemtag') . $r;

// Upgrade the DB first if required

$currentversion = $dbversion = readparameter('schemaversion');

if ($currentversion >= $schemaversion)
{
	echo '-- Database already up to date -- ' . $r;
}
else
{
	echo "eLAS database needs to upgrade from $currentversion to $schemaversion\n";
	while($currentversion < $schemaversion)
	{
		$currentversion++;
		if(doupgrade($currentversion))
		{
			$doneversion = $currentversion;
		}
	}
	$currentversion = readparameter('schemaversion', true);	
	echo "Upgraded database from schema version $dbversion to $currentversion\n";
	log_event("","DB","Upgraded database from schema version $dbversion to $currentversion");	
}


// sync the image files  
$s3 = Aws\S3\S3Client::factory(array(
	'signature'	=> 'v4',
	'region'	=> 'eu-central-1',
	'version'	=> '2006-03-01',
));

echo 'Sync the image files.' . $r;

$possible_extensions = array('jpg', 'jpeg', 'JPG', 'JPEG');

$rs = $db->prepare('SELECT id, "PictureFile" FROM users WHERE "PictureFile" IS NOT NULL');

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
		if($s3->doesObjectExist(getenv('S3_BUCKET'), $filename_bucket))
		{
			$found = true;
			break;
		}
	}

	if (!$found)
	{
		$db->update('users', array('"PictureFile"' => null), array('id' => $user_id));
		echo 'Profile image not present, deleted in database: ' . $filename . $r;
		log_event ($s_id, 'cron', 'Profile image file of user ' . $user_id . ' was not found in bucket: deleted from database. Deleted filename : ' . $filename);
	}
	else if ($f_schema != $schema)
	{
		$new_filename = $schema . '_u_' . $user_id . '_' . sha1(time() . $filename) . '.jpg';
		$result = $s3->copyObject(array(
			'Bucket'		=> getenv('S3_BUCKET'),
			'CopySource'	=> getenv('S3_BUCKET') . '/' . $filename_bucket,
			'Key'			=> $new_filename,
			'ACL'			=> 'public-read',
			'CacheControl'	=> 'public, max-age=31536000',
		));

		if ($result && $result instanceof \Guzzle\Service\Resource\Model)
		{
			$db->update('users', array('"PictureFile"' => $new_filename), array('id' => $user_id));
			echo 'Profile image renamed, old: ' . $filename . ' new: ' . $new_filename . $r;
			log_event($s_id, 'init', 'Profile image file renamed, Old: ' . $filename . ' New: ' . $new_filename);

			$s3->deleteObject(array(
				'Bucket'	=> getenv('S3_BUCKET'),
				'Key'		=> $filename_bucket,
			));
		}
	}
}

$message_images = $db->fetchAll('SELECT id, msgid, "PictureFile" FROM msgpictures');

foreach($message_images as $image)
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
		if($s3->doesObjectExist(getenv('S3_BUCKET'), $filename_bucket))
		{
			$found = true;
			break;
		}
	}

	if (!$found)
	{
		$db->delete('msgpictures', array('id' => $id));
		echo 'Message image not present, deleted in database: ' . $filename . $r;
		log_event ($s_id, 'init', 'Image file of message ' . $msg_id . ' not found in bucket: deleted from database. Deleted : ' . $filename . ' id: ' . $id);
	}
	else if ($f_schema != $schema)
	{
		$new_filename = $schema . '_m_' . $msg_id . '_' . sha1(time() . $filename) . '.jpg';
		$result = $s3->copyObject(array(
			'Bucket'		=> getenv('S3_BUCKET'),
			'CopySource'	=> getenv('S3_BUCKET') . '/' . $filename_bucket,
			'Key'			=> $new_filename,
			'ACL'			=> 'public-read',
			'CacheControl'	=> 'public, max-age=31536000',
		));

		if ($result && $result instanceof \Guzzle\Service\Resource\Model)
		{
			$db->update('msgpictures', array('"PictureFile"' => $new_filename), array('id' => $id));
			echo 'Profile image renamed, old: ' . $filename . ' new: ' . $new_filename . $r;
			log_event($s_id, 'init', 'Message image file renamed, Old : ' . $filename . ' New: ' . $new_filename);

			$s3->deleteObject(array(
				'Bucket'	=> getenv('S3_BUCKET'),
				'Key'		=> $filename_bucket,
			));
		}
	}
}

$schemas = $db->fetchAll('select schema_name from information_schema.schemata');

$schemas = array_map(function($row){ return $row['schema_name']; }, $schemas);

$schemas = array_fill_keys($schemas, true);

echo '* Cleanup files in bucket without valid schema prefix *' . $r;

$results = $s3->getPaginator('ListObjects', array(
	'Bucket' => getenv('S3_BUCKET')
));

foreach ($results as $result)
{
	foreach ($result['Contents'] as $object)
	{
		$key = $object['Key'];

		list($sch, $type, $type_id, $hash) = explode('_', $key);

		if ($schemas[$sch])
		{
			continue;
		}

		$s3->deleteObject(array(
			'Key'		=> $key,
			'Bucket'	=> getenv('S3_BUCKET'),
		));

		echo 'Image deleted from bucket: ' . $key . $r;
		log_event($s_id, 'init', 'Image deleted from bucket: ' . $key);
	}
}

echo 'Sync image files ready.' . $r;

echo 'Cleanup orphaned contacts. ' . $r;

$orphaned_contacts = array();

$rs = $db->prepare('select c.id, c.value
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
	$db->executeQuery('delete * from contact where id IN (?)',
		array(implode(', ', array_keys($orphaned_contacts))),
		array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
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

echo '*** clear users cache ***';

$users = $db->fetchArray('select id from users');

foreach ($users as $u)
{
	$redis->del($schema . '_user_' . $u['id']);
}
echo "\n";

echo '** end **';


