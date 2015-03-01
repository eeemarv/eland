<?php
/**
 * Class to perform eLAS AMQ operations
 *
 * This file is part of eLAS http://elas.vsbnet.be
 *
 * Copyright(C) 2009 Guy Van Sanden <guy@vsbnet.be>
 *
 * eLAS is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the  * GNU General Public License for more details.
*/
/** Provided functions:
 * amq_addmessage ($queue, $message)	Add a message to a queue
 * maildroid_queue($json)				Queue to maildroid
*/

function amq_publishtransaction($posted_list,$fromuser,$touser){
	global $db;
	global $provider;
	global $baseurl;
	$systemtag = readconfigfromdb("systemtag");

	$exchange = $provider->amqexchange;
	$queue = "transactions";
	
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
    
    $params = array('delivery_mode' => 2);

	$jsonarray = array();
	$jsonarray['transid'] = $posted_list['transid'];
	$jsonarray['wwid_from'] = $fromuser['login'] ."@" .$baseurl;
	//$jsonarray['wwid_to'] = 
	$jsonarray['description'] = $posted_list['description'];
	$jsonarray['date'] = $posted_list['date'];
	$jsonarray['amount'] = $posted_list['amount'];
	
	$json = json_encode($jsonarray);
	echo $json;
	echo "\n";
	
	$mex->publish($json, $queue, AMQP_NOPARAM, $params);
	$cnn->disconnect();

}

function amq_sendmail($listname, $subject, $body, $moderationflag) {
	global $db;
	global $provider;
	global $_SESSION;
	
	$systemtag = readconfigfromdb("systemtag");
	
		//echo "DEBUG: Connecting to amqhost " .$provider->amqhost ."\n";
	$exchange = $provider->amqexchange;
	$queue = "emessenger.in";
	
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
    
    $params = array('delivery_mode' => 2);
	
	#{"action":"listsync","mode":"bulk","systemtag":"letsdev","lists"
	$jsonarray = array();
	$jsonarray['action'] = 'elasmail';
	$jsonarray['systemtag'] = $systemtag;
	$jsonarray['listname'] = $listname;
	$jsonarray['subject'] = $subject;
	$jsonarray['mailfrom'] = $_SESSION["email"];
	$jsonarray['fullname'] = $_SESSION["fullname"];
	$jsonarray['body'] = $body ."<p>&nbsp</p>";
	$jsonarray['moderationflag'] = $moderationflag;
	
	$json = json_encode($jsonarray);
	//print $json;
	
	if($mex->publish($json, $queue, AMQP_NOPARAM, $params) == true){
		$cnn->disconnect();
		return 0;
	} else {
		$cnn->disconnect();
		return 1;
	}
}

function amq_publishmail() {
	// FIXME: Add code to send a json encapsulated mail to emessengerd
}

function amq_publishsubcribers(){
	global $db;
	global $provider;
	$systemtag = readconfigfromdb("systemtag");
	#$json = "{ \"object\" : \"lists\" , \"action\" : \"delete\" , \"systemtag\" : \"test\"}";
	
	//echo "DEBUG: Connecting to amqhost " .$provider->amqhost ."\n";
	$exchange = $provider->amqexchange;
	$queue = "emessenger.in";
	
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
    
    $params = array('delivery_mode' => 2);

	$listquery = "SELECT * FROM lists WHERE type = 'internal'";
	$lists = $db->GetArray($listquery);
	foreach($lists as $listkey => $listvalue){
		$listname = $listvalue['listname'];
		$subquery = "SELECT * FROM contact WHERE id_user IN (SELECT user_id FROM listsubscriptions WHERE listname = '" .$listvalue['listname'] ."') AND id_type_contact = 3";
		
		$subscribers = $db->GetArray($subquery);
		
		if(count($subscribers) > 0) {
			$jsonarray = array();
			$jsonarray['action'] = "subsync";
			$jsonarray['mode'] = "bulk";
			$jsonarray['systemtag'] = $systemtag;
			$jsonarray['listname'] = $listname;
			$subscriberarray = array();
	
			$loop = 0;
			//var_dump($subscribers);
			foreach($subscribers as $subkey => $subvalue) {
				#$subvalue['value'];
				//var_dump($subvalue);
				//$json = $json ."{ \"email\" : \"" .$subvalue['value'] ."\" }";
				$subscriberarray['id'] = $subvalue['id_user'];
				$subscriberarray['email'] = $subvalue['value'];
				$jsonarray['subscribers'][$loop] = $subscriberarray;
				$loop = $loop + 1;
			}
			$json = json_encode($jsonarray);
			//echo $json;
			//echo "\n";
			$mex->publish($json, $queue, AMQP_NOPARAM, $params);
		}
	}
		$cnn->disconnect();
}

function amq_publishmailinglists() {
	global $db;
	global $provider;
	$systemtag = readconfigfromdb("systemtag");
	#$json = "{ \"object\" : \"lists\" , \"action\" : \"delete\" , \"systemtag\" : \"test\"}";
	
	//echo "DEBUG: Connecting to amqhost " .$provider->amqhost ."\n";
	$exchange = $provider->amqexchange;
	$queue = "emessenger.in";
	
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
    
    $params = array('delivery_mode' => 2);
    
    $listquery = "SELECT * FROM lists WHERE type = 'internal'";
	$lists = $db->GetArray($listquery);
	$loop = 0;
	
	$jsonarray = array();
	$jsonarray['action'] = "listsync";
	$jsonarray['mode'] = "bulk";
	$jsonarray['systemtag'] = $systemtag;
	$listarray = array();
	
	foreach($lists as $key => $value){
		$listarray['listname'] = $value['listname'];
		$listarray['topic'] = $value['topic'];
		$listarray['description'] = $value['description'];
		$listarray['auth'] = $value['auth'];
		$listarray['moderation'] = $value['moderation'];
		$listarray['moderatormail'] = $value['moderatormail'];
		$jsonarray['lists'][$loop] = $listarray;
		$loop = $loop + 1;
	}
	$json = json_encode($jsonarray);
	//echo $json;
	 
	$mex->publish($json, $queue, AMQP_NOPARAM, $params);
	$cnn->disconnect();
}


function amq_processincoming(){
	global $provider;
	global $db;
	$systemtag = readconfigfromdb("systemtag");
	
	//echo "Running amq_processincoming";
	//echo "DEBUG: Connecting to amqhost " .$provider->amqhost ."\n";
	$cnn = new AMQPConnection();
    $cnn->setHost($provider->amqhost);
    $cnn->connect();
     
    // Create a channel
    $ch = new AMQPChannel($cnn);
    // Declare a new exchange
    $ex = new AMQPExchange($ch);
    //echo "DEBUG: Connecting to Exchange $systemtag\n";
    $ex->setName($systemtag);
    $ex->setType('direct');
    $ex->setFlags(AMQP_DURABLE);
    $ex->declare();
    
    // Create a new queue
    $mq = new AMQPQueue($ch);
    $qn = $systemtag .".incoming";
    //echo "DEBUG: Connecting to queue incoming\n";
    $mq->setName($qn);
    $mq->setFlags(AMQP_DURABLE);
    $mq->declare();
    $mq->bind($systemtag, $systemtag. ".incoming");
    
    #$msg = $q->consume();
    $msgcount = 0;
    while($message = $mq->get()) {
		$msgcount = $msgcount + 1;
		echo "    Processing incoming message #" . $msgcount ."\n";
		echo $message->getBody() ."\n";
		$myjson = json_decode($message->getBody());
		//var_dump($myjson);
		if($myjson->{'action'} == "config"){
			$setting = $myjson->{'setting'};
			$value = $myjson->{'value'};
			echo "Updating config item " . $myjson->{'setting'} . " with value " . $myjson->{'value'} . "\n";
			writeconfig($setting,$value);
		}
	$dtag = $message->getDeliveryTag();
	#echo "Acking " . $dtag;
	$mq->ack($dtag);
	}
	$cnn->disconnect();
}
