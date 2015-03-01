<?php 
 
/**
 * @uri /resources/mailinglist/{Listname}
 */

ob_start();
$rootpath="./";
//$serverbase = $_SERVER['HTTP_HOST'];
require_once($rootpath ."includes/inc_default.php");
require_once($rootpath ."includes/inc_adoconnection.php");
require_once($rootpath ."includes/inc_mailinglists.php");
//require_once($rootpath."includes/inc_apikeys.php");

class Mailinglist extends Resource {
	//
	function get($request, $Listname){
		session_start();
		$s_id = $_SESSION["id"];
		$s_name = $_SESSION["name"];
		$s_letscode = $_SESSION["letscode"];
		$s_accountrole = $_SESSION["accountrole"];
		
		var_dump(get_mailinglist($listname));
	}	
	
	function post($request){
		global $db;
		session_start();
		$s_id = $_SESSION["id"];
		$s_name = $_SESSION["name"];
		$s_letscode = $_SESSION["letscode"];
		$s_accountrole = $_SESSION["accountrole"];
		
		$response = new Response($request);
		
		if($s_accountrole == 'admin'){
			//$response->code = Response::OK;
			//$response->body = var_dump($_POST);
			
			$_POST['type'] = 'internal';
			$_POST['subscribers'] = 'list';
			//description
			//topic
			//auth
			//moderation
			//moderatormail
			
			$missing = 0;
			$erorrmsg = '';
			
			if(empty($_POST['listname'])) {
				$missing = $missing + 1;
				$errormsg = $errormsg . " || Lijstnaam ontbreekt";
			}
			
			if(empty($_POST['description'])) {
				$missing = $missing + 1;
				$errormsg = $errormsg . " || Omschrijving ontbreekt";
			}
			
			if(empty($_POST['topic'])) {
				$missing = $missing + 1;
				$errormsg = $errormsg . " || Onderwerp ontbreekt";
			}
			
			if(empty($_POST['auth'])) {
				$missing = $missing + 1;
				$errormsg = $errormsg . " || Autorisatie ontbreekt";
			}
			
			if(empty($_POST['moderation'])) {
				$missing = $missing + 1;
				$errormsg = $errormsg . " || Moderatie ontbreekt";
			}
			
			if(empty($_POST['moderatormail'])) {
				$missing = $missing + 1;
				$errormsg = $errormsg . " || Moderatiemail ontbreekt";
			}
			
			if($missing > 0) {
				$response->code = Response::OK;
				$response->body = "Fout: " .$errormsg;
			} else {
				if($db->AutoExecute("lists", $_POST, 'INSERT') == TRUE){
					$response->code = Response::CREATED;
					$response->body = "OK - Mailinglist aangemaakt";
				} else {
					$response->code = Response::OK;
					$response->body = "Fout: Lijst kon niet worden toegevoegd";
				}
			}
		} else {
			$response->code = Response::UNAUTHORIZED;
			$response->body = "Onvoldoende rechten";
		}
		
		$response->addHeader('Content-type', 'text/plain');
 		return $response;
	}
}

?>
