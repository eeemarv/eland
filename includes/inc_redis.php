<?php

require_once($rootpath."vendor/autoload.php");

// Connect to Redis

$redis_url = getenv('REDISTOGO_URL');

if(!empty($redis_url)){
	Predis\Autoloader::register();
	try {
		$redis_con = parse_url($redis_url);
		$redis_con['password'] = $redis_con['pass'];
		$redis_con['scheme'] = 'tcp';
	    $redis = new Predis\Client($redis_con);

	}
	catch (Exception $e) {
	    echo "Couldn't connected to Redis: ";
	    echo $e->getMessage();
	}
}
