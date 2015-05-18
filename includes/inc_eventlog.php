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

//Download the elas log in json format
function get_elaslog() {
	global $rootpath;
	global $provider;
	global $baseurl;

//	$logurl = $provider->logurl;

	$url = "$logurl/site?tag=" . readconfigfromdb("systemtag");

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$resp = curl_exec($ch);
	if(curl_errno($ch)) {
		$msg = 'Curl error: ' . curl_error($ch);
		log_event(0, 'Info', $msg);
	}

	curl_close($ch);

	//print $resp;

	# Write response to a file
	$file = "$rootpath/sites/$baseurl/json/eventlog.json";
	file_put_contents($file, $resp);
}

//Write log entry
function log_event($id, $type, $event){

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

/*
	$ip = $_SERVER['REMOTE_ADDR'];

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

   	$ts = date("Y-m-d H:i:s");
	$mytype = strtolower($type);

	//Put log message on queue
	if ($configuration["hosting"]["enabled"]	== 1){
		$systemtag = readconfigfromdb("systemtag");

		$exchange = "elas";
		$queue = "elaslog";

		$cnn = new AMQPConnection();
		$cnn->setHost($provider->amqhost);
		$cnn->connect();

		// Create a channel
		$ch = new AMQPChannel($cnn);
		// Declare a new exchange
		$mex = new AMQPExchange($ch);
		$mex->setName($exchange);
		$mex->setType('direct');
		$mex->setFlags(AMQP_DURABLE);
		$mex->declare();

		// Create a new queue
		$mailq = new AMQPQueue($ch);
		$mailq->setName($queue);
		$mailq->setFlags(AMQP_DURABLE);
		$mailq->declare();
		$mailq->bind($exchange, $queue);

		$jsonarray = array();
		$jsonarray['source'] = "elas";
		$jsonarray['systemtag'] = $systemtag;
		$jsonarray['userid'] = $id;
		$jsonarray['type'] = $mytype;
		$jsonarray['timestamp'] = $ts;
		if(!empty($id) && $id != " ")
		{
			$jsonarray['event'] = $event . " (" .$id .")";
		} else {
			$jsonarray['event'] = $event;
		}
		$jsonarray['ip'] = $ip;
		$json = json_encode($jsonarray);
		//print $json;

		// Only send debug if debug is enabled
		if($mytype != 'debug' || $elasdebug == 1) {
			$mex->publish($json, $queue);
		}

	} */
}
