<?php

/**
 * @uri /resources/interlets/status
 */

$rootpath="./";
//$serverbase = $_SERVER['HTTP_HOST'];
require_once($rootpath ."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_apikeys.php");

class InterletsStatus extends Resource {
	public static function get($request){
		$response = new Response($request);
		$response->addHeader('Content-type', 'text/plain');

		global $elasversion;
		//if(check_apikey($apikey,"interlets") == 1){
			if(readconfigfromdb("maintenance") == 1){
				$result = "OFFLINE";
			} else {
				$result = "OK - eLAS $elasversion";
			}
		//} else {
			//return "APIKEYFAIL";
		//}

		$response->body = $result;
		return $response;

	}

	public function post($request, $apikey){
		parse_str($request->data, $input);
		$response = new Response($request);
		$response->addHeader('Content-type', 'text/plain');

		global $elasversion;
		if(check_apikey($apikey,"interlets") == 1){
			if(readconfigfromdb("maintenance") == 1){
				$result = "OFFLINE";
			} else {
				$result = "OK - eLAS $elasversion";
				$response->body = $result;
				return $response;
			}
		} else {
			//return "APIKEYFAIL";
			$response->body = "APIKEYFAIL" . " / " .$input;
			return $response;
		}
	}

}

?>
