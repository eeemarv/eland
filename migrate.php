<?php
$rootpath = './';
$page_access = 'admin';
require_once $rootpath . 'includes/inc_default.php';

if ($s_id != 'master')
{
	redirect_index();
}

$type = $_GET['type'] ?: false;

$r = "<br>\r\n";
header('Content-Type:text/html');
echo '*** migrate eLAND (temporal script) ***' . $r;

if (!$type)
{
	echo 'Error: Set a type.';
	exit;
}

/*
                            Table "eland_extra.events"
   Column    |            Type             |              Modifiers               
-------------+-----------------------------+--------------------------------------
 ts          | timestamp without time zone | default timezone('utc'::text, now())
 user_id     | integer                     | default 0
 user_schema | character varying(60)       | 
 agg_id      | character varying(255)      | 
 agg_type    | character varying(60)       | 
 agg_version | integer                     | 
 data        | jsonb                       | 
 event_time  | timestamp without time zone | default timezone('utc'::text, now())
 ip          | character varying(60)       | 
 event       | character varying(128)      | 
*/

$mdb->connect();
$mclient = $mdb->get_client();

if ($type == 'user')
{
	$user_agg_ids = [];
	$fullname_access_ary = [];

	foreach ($schemas as $s)
	{
		$users_collection = $s . '_users';

		$users = $mclient->$users_collection->find();

		foreach ($users as $u)
		{
			$user_agg_ids[] = $s . '_user_' . $u['id'];
			$fullname_access_ary[$s][$u['id']] = $access_control->get_role($u['fullname_access']);
		}
	}

	$stored_ary = [];

	$rows = $db->executeQuery('select e1.agg_id,
		e1.agg_version,
		e1.data->>\'fullname_access\' as fullname_access
		from eland_extra.events e1
		where e1.agg_version = (select max(e2.agg_version)
				from eland_extra.events e2
				where e1.agg_id = e2.agg_id)
			and e1.agg_type = \'user\'
			and agg_id in (?)',
			[$user_agg_ids], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

	foreach ($rows as $row)
	{
		$stored_ary[$row['agg_id']] = [
			'agg_version'		=> $row['agg_version'],
			'fullname_access'	=> $row['fullname_access'],
		];
	}

	foreach ($fullname_access_ary as $s => $user_fullname_access_ary)
	{
		foreach ($user_fullname_access_ary as $user_id => $fullname_access)
		{
			echo $s . ' -- ';
			echo link_user($user_id, $s, false, true);
			echo ' fullname visibility: ';
			echo $fullname_access;

			$agg_id = $s . '_user_' . $user_id;

			if ($stored_ary[$agg_id])
			{
				echo ' (version: ' . $stored_ary[$agg_id]['agg_version'] . ') ';
			}

			if (!$stored_ary[$agg_id]
				|| $fullname_access != $stored_ary[$agg_id]['fullname_access'])
			{
				$agg_version = (isset($stored_ary[$agg_id]['agg_version'])) ? $stored_ary[$agg_id]['agg_version'] + 1 : 1;

				$db->insert('eland_extra.events', [
					'agg_id'		=> $agg_id,
					'agg_type'		=> 'user',
					'agg_version'	=> $agg_version,
					'data'			=> json_encode(['fullname_access' => $fullname_access]),
					'event'			=> 'user_fullname_access_updated'
				]);

				echo ' UPDATED';
			}

			echo $r;
		}
	}

	echo '--- end ---' . $r;
	exit;
}


