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

	$value = $db->GetOne('SELECT value FROM config WHERE setting = \'' . $key . '\'');

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

	if (!$db->Execute('UPDATE config SET value = \'' . $value . '\', "default" = \'f\' WHERE setting = \'' . $key . '\''))
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

	$value = $db->GetOne('SELECT value FROM parameters WHERE parameter = \'' . $key . '\'');

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

	$user = $db->GetRow('SELECT * FROM users WHERE id = ' . $id);

	if (isset($user))
	{
		$redis->set($redis_key, serialize($user));
		$redis->expire($redis_key, 7200);
		$cache[$id] = $user;
	}

	return $user;
}

/*
function readtotaltransactions($refresh = false)
{
	global $db, $redis;
    static $cache;

	$redis_key = $schema . '_total_transactions';	

	if (!$refresh)
	{
		if (isset($cache))
		{
			return $cache;
		}

		if ($redis->exists($redis_key))
		{
			return $cache = (int) $redis->get($redis_key);
		} 
	}

	$total_transactions = $db->GetOne('SELECT COUNT(id) FROM transactions');

	if (isset($total_transactions))
	{
		$redis->set($redis_key, $total_transactions);
		$redis->expire($redis_key, 7200);
		$cache = $total_transactions;
	}

	return $total_transactions;
}
*/
