<?php 
 
/**
 * @uri /resources/system/apiversion
 */
 
$rootpath="./";
//$serverbase = $_SERVER['HTTP_HOST'];
require_once($rootpath ."includes/inc_default.php");

class ApiVersion extends Resource {
	public static function get($request){
		$response = new Response($request);
		$response->addHeader('Content-type', 'text/plain');
		
		global $restversion;
		
		$response->body = $restversion;
		return $response;
		
	}
}
 
?>
