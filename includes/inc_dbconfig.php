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
