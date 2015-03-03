<?php

/*
 *
 */
 
function readconfigfromdb($key){
    global $db, $session_name, $redis;
    static $cache;

	if (isset($cache[$key]))
	{
		return $cache[$key];
	}

	$redis_key = $session_name . '_config_' . $key;

	if ($redis->exists($redis_key))
	{
		return $cache[$key] = $redis->get($redis_key);
	}

	$value = $db->GetOne('SELECT value FROM config WHERE setting = \'' . $key . '\'');

	if (isset($value))
	{
		$redis->set($redis_key, $value);
		$redis->expire($rediskey, 7200);
		$cache[$key] = $value;
	}

	return $value;
}


/**
 *
 */
function writeconfig($key, $value)
{
	global $db, $redis, $session_name;
	
	$query = "UPDATE config SET value = '" . $value . "' WHERE setting = '" . $key . "'";
	$result = $db->Execute($query);
	if (!$result)
	{
		return false;
	}

	$redis_key = $session_name . '_config_' . $key;
	$redis->set($redis_key, $value);
	$redis->expire($rediskey, 7200);

	return true;
}

/**
 *
 */
function readparameter($key)
{
    global $db, $session_name, $redis;
    static $cache;

	if (isset($cache[$key]))
	{
		return $cache[$key];
	}

	$redis_key = $session_name . '_parameters_' . $key;

	if ($redis->exists($redis_key))
	{
		return $cache[$key] = $redis->get($redis_key);
	}

	$value = $db->GetOne('SELECT value FROM parameters WHERE parameter = \'' . $key . '\'');

	if (isset($value))
	{
		$redis->set($redis_key, $value);
		$redis->expire($rediskey, 1800);
		$cache[$key] = $value;
	}

	return $value;
}

/**
 * (not used)
 */
function readuser($id, $refresh = false)
{
    global $db, $session_name, $redis;
    static $cache;

	if (!$refresh)
	{
		if (isset($cache[$id]))
		{
			return $cache[$id];
		}

		$redis_key = $session_name . '_user_' . $id;

		if ($redis->exists($redis_key))
		{
			return $cache[$id] = json_decode($redis->get($redis_key));
		}
	}

	$user = $db->GetRow('SELECT * FROM users WHERE id = ' . $id);

	if (isset($user))
	{
		$redis->set($redis_key, json_encode($user));
		$redis->expire($rediskey, 7200);
		$cache[$id] = $user;
	}

	return $user;
}

/**
 * (not used)
 */
function readusercontacts($user_id, $refresh = false)
{
    global $db, $session_name, $redis;
    static $cache;

	if (!$refresh)
	{
		if (isset($cache[$user_id]))
		{
			return $cache[$user_id];
		}

		$redis_key = $session_name . '_user_contacts_' . $user_id;

		if ($redis->exists($redis_key))
		{
			return $cache[$user_id] = json_decode($redis->get($redis_key));
		}
	}

	$contacts = $db->GetRow('SELECT * FROM contact WHERE id_user = ' . $user_id);

	if (isset($contacts))
	{
		$redis->set($redis_key, json_encode($contacts));
		$redis->expire($rediskey, 7200);
		$cache[$user_id] = $contacts;
	}

	return $user;
}
