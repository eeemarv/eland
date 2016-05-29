<?php
require_once $rootpath . 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

function log_event($type, $event, $remote_schema = false)
{
	global $schema, $hosts, $s_schema, $s_id;

	$type = strtolower($type);

	$sch = ($remote_schema) ? $remote_schema : $schema;

	$h = $hosts[$sch];

	$formatter = new ColoredLineFormatter();

	$log = new Logger($sch);
	$streamHandler = new StreamHandler('php://stdout', Logger::NOTICE);
	$streamHandler->setFormatter($formatter);
	$log->pushHandler($streamHandler);

	if ($s_id && $s_schema)
	{
		$user = readuser($s_id false, $s_schema);
		$username = $user['name'];
		$letscode = $user['letscode'];
		$user_str = ' user: ' . link_user($user, $sch, false, true); 
	}
	else
	{
		$username = $letscode = $user_str = '';
	}

	$log->addNotice('eLAND: ' . $sch . ': ' . $h . ': ' .
		$type . ': ' . $event . $user_str . "\n\r");

	$item = array(
		'ts_tz'			=> date('Y-m-d H:i:s'),
		'timestamp'		=> gmdate('Y-m-d H:i:s'),
		'user_id' 		=> $s_id,
		'user_schema'	=> $s_schema,
		'letscode'		=> strtolower($letscode),
		'username'		=> $username,
		'ip'			=> $_SERVER['REMOTE_ADDR'],
		'type'			=> strtolower($type),
		'event'			=> $event,
	);

	register_shutdown_function('insert_log', $item, $remote_schema);
}

function insert_log($item, $remote_schema = null)
{
	global $mdb;

	$mdb->connect();

	if (isset($remote_schema))
	{
		$logs = $remote_schema . '_logs';
		$mdb->get_client()->$logs->insert($item);
		return;
	}

	$mdb->logs->insert($item);
}
