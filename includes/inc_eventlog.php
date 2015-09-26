<?php
require_once $rootpath . 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

function log_event($user_id, $type, $event)
{
	global $elasdebug, $schema, $elas_log;

	$type = strtolower($type);

	$domain = array_search($schema, $_ENV);
	$domain = str_replace('ELAS_SCHEMA_', '', $domain);
	$domain = str_replace('____', ':', $domain);
	$domain = str_replace('___', '-', $domain);
	$domain = str_replace('__', '.', $domain);
	$domain = strtolower($domain);
	$domain = $domain . ' / ' . $_SERVER['HTTP_HOST'];

	$formatter = new ColoredLineFormatter();

	$log = new Logger($schema);
	$streamHandler = new StreamHandler('php://stdout', Logger::NOTICE);
	$streamHandler->setFormatter($formatter);
	$log->pushHandler($streamHandler);

	if ($user_id)
	{
		$user = readuser($user_id);
		$username = $user['name'];
		$letscode = $user['letscode'];
	}
	else
	{
		$username = $letscode = '';
	}

	$log->addNotice('eLAS-Heroku: ' . $schema . ': ' . $domain . ': ' .
		$type . ': ' . $event . ' user id:' . $user_id .
		' user: ' . $letscode . ' ' . $name . "\n\r");

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

	register_shutdown_function('insert_log', $item);	
}

function insert_log($item)
{
	global $elas_mongo;

	$elas_mongo->connect();
	$elas_mongo->logs->insert($item);
}
