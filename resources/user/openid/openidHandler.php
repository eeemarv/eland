<?php

/**
 * @uri /resources/user/{UserID}/openid
 */

ob_start();
$rootpath="./";
//$serverbase = $_SERVER['HTTP_HOST'];
require_once($rootpath ."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
//require_once($rootpath."includes/inc_apikeys.php");

class OpenID extends Resource {
	//
	function get($UserID){
		session_start();
		$s_id = $_SESSION["id"];
		$s_name = $_SESSION["name"];
		$s_letscode = $_SESSION["letscode"];
		$s_accountrole = $_SESSION["accountrole"];

		//echo "Fetching openid for user $UserID";
		echo "Not implemented";

	}

	function post($request, $UserID){
		global $db;
		session_start();
		$s_id = $_SESSION["id"];
		$s_name = $_SESSION["name"];
		$s_letscode = $_SESSION["letscode"];
		$s_accountrole = $_SESSION["accountrole"];

		$openid = $_POST["openid"];
		//echo "Writing openid $openid\n";

		$response = new Response($request);

		// Check if we are allowed to insert an openid for this user
		// If so, check if the openid is already in use (anywhere)
		// If not in use, save it
		if($s_id == $UserID || $s_accountrole == 'admin'){
			if(checkoid($openid) == 1) {
				$response->code = Response::OK;
				$response->body = "OpenID $openid is al in gebruik";
			} else {
				//verify structure
				if(filter_var("$openid", FILTER_VALIDATE_URL)){
					$posted_list["user_id"] = $UserID;
					$posted_list["openid"] = $openid;
					if($db->AutoExecute("openid", $posted_list, 'INSERT') == TRUE){
						$response->code = Response::CREATED;
						$response->body = "OK - OpenID $openid Opgeslagen";
					} else {
						$response->code = Response::OK;
						$response->body = "OpenID $openid kon niet worden opgeslagen";
					}
				} else {
					$response->code = Response::OK;
					$response->body = "Ongeldig OpenID";
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

function checkoid($openid) {
		global $db;
		$query = "SELECT * FROM openid WHERE openid = '" . $openid ."'";
		$result = $db->GetArray($query);
        if(count($result) > 0){
                return 1;
        } else {
                return 0;
        }
}

?>
