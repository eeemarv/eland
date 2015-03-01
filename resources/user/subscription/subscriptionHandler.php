<?php 
 
/**
 * @uri /resources/user/subscription/{Sub}
 */

ob_start();
$rootpath="./";
//$serverbase = $_SERVER['HTTP_HOST'];
require_once($rootpath ."includes/inc_default.php");
require_once($rootpath ."includes/inc_adoconnection.php");
require_once($rootpath ."includes/inc_mailinglists.php");
require_once($rootpath ."includes/inc_amq.php");
//require_once($rootpath."includes/inc_apikeys.php");

class subscriptionHandler extends Resource {
	//
	function get($request, $Msg){
		echo "GET subscription not implemented";
	}	
	
	function post($request, $Msg){
		global $db;
		session_start();
		$s_id = $_SESSION["id"];
		$s_name = $_SESSION["name"];
		$s_letscode = $_SESSION["letscode"];
		$s_accountrole = $_SESSION["accountrole"];
		
		$response = new Response($request);
	}
		
}

?>
