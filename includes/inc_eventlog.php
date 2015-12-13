<?php
require_once $rootpath . 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

function log_event($user_id, $type, $event, $remote_schema = null)
{
	global $schema, $elas_log;

	$type = strtolower($type);

	$sch = (isset($remote_schema)) ? $remote_schema : $schema;

	$domain = array_search($sch, $_ENV);
	$domain = str_replace('SCHEMA_', '', $domain);
	$domain = str_replace('____', ':', $domain);
	$domain = str_replace('___', '-', $domain);
	$domain = str_replace('__', '.', $domain);
	$domain = strtolower($domain);
	$domain = $domain . ' / ' . $_SERVER['HTTP_HOST'];

	$formatter = new ColoredLineFormatter();

	$log = new Logger($sch);
	$streamHandler = new StreamHandler('php://stdout', Logger::NOTICE);
	$streamHandler->setFormatter($formatter);
	$log->pushHandler($streamHandler);

	if ($user_id)
	{
		$user = readuser($user_id, false, $sch);
		$username = $user['name'];
		$letscode = $user['letscode'];
	}
	else
	{
		$username = $letscode = '';
	}

	$log->addNotice('eLAS-Heroku: ' . $sch . ': ' . $domain . ': ' .
		$type . ': ' . $event . ' user id:' . $user_id .
		' user: ' . $letscode . ' ' . $username . "\n\r");

	$item = array(
		'ts_tz'		=> date('Y-m-d H:i:s'),
		'timestamp'	=> gmdate('Y-m-d H:i:s'),
		'user_id' 	=> $user_id,
		'letscode'	=> strtolower($letscode),
		'username'	=> $username,
		'ip'		=> $_SERVER['REMOTE_ADDR'],
		'type'		=> strtolower($type),
		'event'		=> $event,
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
