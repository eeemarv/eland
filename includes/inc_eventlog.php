<?php
require_once $rootpath . 'vendor/autoload.php';
require_once $rootpath . 'includes/inc_elas_heroku_log.php';

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

$elas_log = new elas_heroku_log($schema);

register_shutdown_function('elas_log_flush');

function elas_log_flush()
{
	global $elas_log;

	$elas_log->flush();
}


function log_event($id, $type, $event)
{

	global $elasdebug, $schema, $elas_log;

	$type = strtolower($type);

	//find domain from session / real domain

	$domain = array_search($schema, $_ENV);
	$domain = str_replace('ELAS_SCHEMA_', '', $domain);
	$domain = str_replace('____', ':', $domain);
	$domain = str_replace('___', '-', $domain);
	$domain = str_replace('__', '.', $domain);
	$domain = strtolower($domain);
	$domain = $domain . ' / ' . $_SERVER['HTTP_HOST'];

	// formatter
	$formatter = new ColoredLineFormatter();

	// create a log channel to STDOUT
	$log = new Logger($schema);
	$streamHandler = new StreamHandler('php://stdout', Logger::NOTICE);
	$streamHandler->setFormatter($formatter);
	$log->pushHandler($streamHandler);

	// messages
	$log->addNotice('eLAS-Heroku: ' . $schema . ': ' . $domain . ': ' . $type . ': ' . $event . ' user id:' . $id . "\n\r");

	$elas_log->insert($id, $type, $event);
}
