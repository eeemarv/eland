<?php 
 
/**
 * @uri /resources/mailinglist/message/{Msg}
 */

ob_start();
$rootpath="./";
//$serverbase = $_SERVER['HTTP_HOST'];
require_once($rootpath ."includes/inc_default.php");
require_once($rootpath ."includes/inc_adoconnection.php");
require_once($rootpath ."includes/inc_mailinglists.php");
require_once($rootpath ."includes/inc_amq.php");
//require_once($rootpath."includes/inc_apikeys.php");

class MailMessage extends Resource {
	//
	function get($request, $Msg){
		echo "GET message not implemented";
	}	
	
	function post($request, $Msg){
		global $db;
		session_start();
		$s_id = $_SESSION["id"];
		$s_name = $_SESSION["name"];
		$s_letscode = $_SESSION["letscode"];
		$s_accountrole = $_SESSION["accountrole"];
		
		$response = new Response($request);
		
		if($s_accountrole == 'admin' || $s_accountrole == 'user'){
			$list = get_mailinglist($_POST['list']);
			if($list['auth'] == 'closed' &&  $s_accountrole != 'admin') {
				$response->code = Response::UNAUTHORIZED;
				$response->body = "Gesloten lijst is enkel toegankelijk voor beheerders";
			} else {
				// We are cleared to send, so let's do it here
				if($s_accountrole == 'admin'){
					$moderationflag = 0;
				} else {
					$moderationflag = 1;
				}
				
				if(amq_sendmail($_POST['list'], $_POST['msgsubject'], $_POST['msgbody'], $moderationflag) == 0) {
					$response->code = Response::OK;
					$response->body = "OK - Bericht verzonden";
				} 
					#$response->code = Response::OK;
					#$response->body = "FOUT - Bericht kon niet worden verzonden"
				
			}
		} else {
			$response->code = Response::UNAUTHORIZED;
			$response->body = "Onvoldoende rechten";
		}
		
		#$response->code = Response::OK;
		#$response->body = var_dump($_POST);
		$response->addHeader('Content-type', 'text/plain');
 		return $response;
 		
	}
}

?>
