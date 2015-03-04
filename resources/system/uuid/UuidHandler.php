<?php

/**
 * @uri /resources/system/uuid
 */

$rootpath="./";
//$serverbase = $_SERVER['HTTP_HOST'];
require_once($rootpath ."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_apikeys.php");

class Uuid extends Resource {
	public static function get($request){
		$response = new Response($request);
		$response->addHeader('Content-type', 'text/plain');

		$uuid = readparameter("uuid");
		if(!empty($uuid)) {
			$response->body = $uuid;
		} else {
			$response->body = "NULL";
		}
		return $response;

	}
}

?>
