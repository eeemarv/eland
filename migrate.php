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
 agg_id      | character varying(255)      | not null
 agg_type    | character varying(60)       | 
 agg_version | integer                     | not null
 data        | jsonb                       | 
 event_time  | timestamp without time zone | default timezone('utc'::text, now())
 ip          | character varying(60)       | 
 event       | character varying(128)      | 
 agg_schema  | character varying(60)       | 
 eland_id    | character varying(40)       | 
Indexes:
    "events_pkey" PRIMARY KEY, btree (agg_id, agg_version)

                             Table "eland_extra.aggs"
   Column    |            Type             |              Modifiers               
-------------+-----------------------------+--------------------------------------
 agg_id      | character varying(255)      | not null
 agg_version | integer                     | not null
 data        | jsonb                       | 
 user_id     | integer                     | default 0
 user_schema | character varying(60)       | default ''::character varying
 ts          | timestamp without time zone | default timezone('utc'::text, now())
 agg_type    | character varying(60)       | not null
 agg_schema  | character varying(60)       | not null
 ip          | character varying(60)       | 
 event       | character varying(128)      | 
 eland_id    | character varying(40)       | 
Indexes:
    "aggs_pkey" PRIMARY KEY, btree (agg_id)
    "aggs_agg_schema_idx" btree (agg_schema)
    "aggs_agg_type_agg_schema_idx" btree (agg_type, agg_schema)
    "aggs_agg_type_idx" btree (agg_type)

	$rows = $db->executeQuery('select e1.agg_id,
		e1.agg_version,
		e1.data
		from eland_extra.events e1
		where e1.agg_version = (select max(e2.agg_version)
				from eland_extra.events e2
				where e1.agg_id = e2.agg_id)
			and e1.agg_type = \'setting\'
			and e1.agg_id in (?)',
			[$setting_agg_ids], [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
*/

$mdb->connect();
$mclient = $mdb->get_client();

if ($type == 'user_fullname_access')
{
	$agg_id_ary = [];
	$fullname_access_ary = [];

	foreach ($schemas as $s)
	{
		$users_collection = $s . '_users';

		$users = $mclient->$users_collection->find();

		foreach ($users as $u)
		{
			$agg_id_ary[] = $s . '_user_fullname_access_' . $u['id'];
			$fullname_access_ary[$s][$u['id']] = $access_control->get_role($u['fullname_access']);
		}
	}

	$stored_ary = $exdb->get_many(['agg_type' => 'user_fullname_access', 'agg_id_ary' => $agg_id_ary]);

	foreach ($fullname_access_ary as $s => $user_fullname_access_ary)
	{
		foreach ($user_fullname_access_ary as $user_id => $fullname_access)
		{
			echo $s . ' -- ';
			echo link_user($user_id, $s, false, true);
			echo ' fullname visibility: ';
			echo $fullname_access;

			$agg_id = $s . '_user_fullname_access_' . $user_id;

			if (isset($stored_ary[$agg_id]))
			{
				echo ' (version: ' . $stored_ary[$agg_id]['agg_version'] . ') ';
			}

			if (!isset($stored_ary[$agg_id])
				|| $fullname_access != $stored_ary[$agg_id]['data']['fullname_access'])
			{
				$err = $exdb->set('user_fullname_access', $user_id, ['fullname_access' => $fullname_access], $s);

				if ($err)
				{
					echo ' ' . $err;
				}
				else
				{
					echo ' UPDATED';
				}
			}

			echo $r;
		}
	}

	echo '--- end ---' . $r;
	exit;
}

if ($type == 'setting')
{
	$agg_id_ary = [];
	$setting_ary = [];

	foreach ($schemas as $s)
	{
		$settings_collection = $s . '_settings';

		$settings = $mclient->$settings_collection->find();

		foreach ($settings as $setting)
		{
			$agg_id_ary[] = $s . '_setting_' . $setting['name'];

			$data = $setting;
			unset($data['_id'], $data['name']);

			$setting_ary[$s][$setting['name']] = $data;
		}
	}

	$stored_ary = $exdb->get_many(['agg_type' => 'setting', 'agg_id_ary' => $agg_id_ary]);

	foreach ($setting_ary as $s => $schema_settings)
	{
		foreach ($schema_settings as $setting_id => $data)
		{
			echo $s . ' -- ';
			echo ' setting: ' . $setting_id;
			echo ': ';
			echo $value;

			$agg_id = $s . '_setting_' . $setting_id;

			if (isset($stored_ary[$agg_id]))
			{
				echo ' (version: ' . $stored_ary[$agg_id]['agg_version'] . ') ';
			}

			if (!isset($stored_ary[$agg_id])
				|| $data != $stored_ary[$agg_id]['data'])
			{
				$err = $exdb->set('setting', $setting_id, $data, $s);

				if ($err)
				{
					echo ' ' . $err;
				}
				else
				{
					echo ' UPDATED';
				}
			}

			echo $r . $r;
		}
	}

	echo '--- end ---' . $r;
	exit;
}

if ($type == 'forum')
{
	$agg_id_ary = [];
	$forum_ary = [];

	foreach ($schemas as $s)
	{
		$forum_collection = $s . '_forum';

		$forum_posts = $mclient->$forum_collection->find();

		foreach ($forum_posts as $forum_post)
		{
			$p = $forum_post['_id']->__toString();

			$agg_id_ary[] = $s . '_forum_' . $p;

			$forum_ary[$s][$p] = $forum_post;
		}
	}

	$stored_ary = $exdb->get_many(['agg_type' => 'forum', 'agg_id_ary' => $agg_id_ary]);

	foreach ($forum_ary as $s => $forum_post_ary)
	{
		foreach ($forum_post_ary as $id => $data)
		{
			echo $s . ' -- ';
			echo ' forum_post: ' . $id;
			echo ': ';
			echo json_encode($data);

			$agg_id = $s . '_forum_' . $id;

			if (isset($stored_ary[$agg_id]))
			{
				echo ' (version: ' . $stored_ary[$agg_id]['agg_version'] . ') ';
			}

			if (!isset($stored_ary[$agg_id])
				|| $data != $stored_ary[$agg_id]['data'])
			{

				set_forum_post($data, $s);

				echo ' UPDATED';
			}

			echo $r . $r;
		}
	}

	echo '--- end ---' . $r;
	exit;
}
