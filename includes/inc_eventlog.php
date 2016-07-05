<?php
require_once $rootpath . 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

function log_event($type, $event, $remote_schema = false)
{
	global $db, $schema, $hosts, $s_schema, $s_id, $s_master, $s_elas_guest;

	$type = strtolower($type);

	$sch = ($remote_schema) ? $remote_schema : $schema;

	$h = $hosts[$sch];

	$formatter = new ColoredLineFormatter();

	$log = new Logger($sch);
	$streamHandler = new StreamHandler('php://stdout', Logger::NOTICE);
	$streamHandler->setFormatter($formatter);
	$log->pushHandler($streamHandler);

	if ($s_master)
	{
		$username = $user_str = 'master';
		$letscode = '';
	}
	else if ($s_elas_guest)
	{
		$username = $user_str = 'elas_guest';
		$letscode = '';
	}
	else if ($s_id && $s_schema)
	{
		$user = readuser($s_id, false, $s_schema);
		$username = $user['name'];
		$letscode = $user['letscode'];
		$user_str = 'user: ' . link_user($user, $sch, false, true); 
	}
	else
	{
		$username = $letscode = $user_str = '';
	}

	$log->addNotice('eLAND: ' . $sch . ': ' . $h . ': ' .
		$type . ': ' . $event . ' ' . $user_str . "\n\r");

	if (isset($_SERVER['HTTP_CLIENT_IP']))
	{
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	}
	else if (isset($_SERVER['HTTP_X_FORWARDE‌​D_FOR']))
	{
		$ip = $_SERVER['HTTP_X_FORWARDE‌​D_FOR'];
	}
	else
	{
		$ip = $_SERVER['REMOTE_ADDR'];
	}

	$item = [
		'ts_tz'			=> date('Y-m-d H:i:s'),
		'timestamp'		=> gmdate('Y-m-d H:i:s'),
		'user_id' 		=> ($s_master || $s_elas_guest) ? 0 : $s_id,
		'user_schema'	=> $s_schema,
		'letscode'		=> strtolower($letscode),
		'username'		=> $username,
		'ip'			=> $ip,
		'type'			=> strtolower($type),
		'event'			=> $event,
	];

	$log_item = [
		'schema'		=> $sch,
		'user_id'		=> ($s_master || $s_elas_guest) ? 0 : (($s_id) ?: 0),
		'user_schema'	=> $s_schema,
		'letscode'		=> strtolower($letscode),
		'username'		=> $username,
		'ip'			=> $ip,
		'type'			=> strtolower($type),
		'event'			=> $event,
	];

	$db->insert('eland_extra.logs', $log_item);

	register_shutdown_function('insert_log', $item, $remote_schema);
}

function insert_log($item, $remote_schema = false)
{
	global $mdb;

	$mdb->connect();

	if ($remote_schema)
	{
		$logs = $remote_schema . '_logs';
		$mdb->get_client()->$logs->insert($item);
		return;
	}

	$mdb->logs->insert($item);
}
