<?php

function readconfigfromdb($key){
    global $db, $schema, $redis;
    static $cache;

	if (isset($cache[$key]))
	{
		return $cache[$key];
	}

	$redis_key = $schema . '_config_' . $key;

	if ($redis->exists($redis_key))
	{
		return $cache[$key] = $redis->get($redis_key);
	}

	$value = $db->fetchColumn('SELECT value FROM config WHERE setting = ?', array($key));

	if (isset($value))
	{
		$redis->set($redis_key, $value);
		$redis->expire($redis_key, 7200);
		$cache[$key] = $value;
	}

	return $value;
}

/**
 *
 */
function writeconfig($key, $value)
{
	global $db, $redis, $schema;

	if (!$db->Execute('UPDATE config SET value = \'' . pg_escape_string($value) . '\', "default" = \'f\' WHERE setting = \'' . $key . '\''))
	{
		return false;
	}

	$redis_key = $schema . '_config_' . $key;
	$redis->set($redis_key, $value);
	$redis->expire($redis_key, 7200);

	return true;
}

/**
 *
 */
function readparameter($key, $refresh = false)
{
    global $db, $schema, $redis;
    static $cache;

	if (!$refresh)
	{
		if (isset($cache[$key]))
		{
			return $cache[$key];
		}

		$redis_key = $schema . '_parameters_' . $key;

		if ($redis->exists($redis_key))
		{
			return $cache[$key] = $redis->get($redis_key);
		}
	}

	$value = $db->fetchColumn('SELECT value FROM parameters WHERE parameter = ?', array($key));

	if (isset($value))
	{
		$redis->set($redis_key, $value);
		$redis->expire($redis_key, 28800);
		$cache[$key] = $value;
	}

	return $value;
}

/**
 *
 */
function readuser($id, $refresh = false)
{
    global $db, $schema, $redis;
    static $cache;

	if (!$id)
	{
		return array();
	}

	$redis_key = $schema . '_user_' . $id;	

	if (!$refresh)
	{
		if (isset($cache[$id]))
		{
			return $cache[$id];
		}

		if ($redis->exists($redis_key))
		{
			return $cache[$id] = unserialize($redis->get($redis_key));
		} 
	}

	$user = $db->fetchAssoc('SELECT * FROM users WHERE id = ?', array($id));

	if (isset($user))
	{
		$redis->set($redis_key, serialize($user));
		$redis->expire($redis_key, 7200);
		$cache[$id] = $user;
	}

	return $user;
}
