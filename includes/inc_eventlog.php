<?php

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
function log_event($id,$type,$event){
	global $configuration;
    	global $db;
	global $elasdebug;
	global $dirbase;
	global $rootpath;

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
		//$jsonarray['userid'] = $id;
		$jsonarray['type'] = $mytype;
		$jsonarray['timestamp'] = $ts;
		if(!empty($id) && $id != " "){
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

	}
}

?>
