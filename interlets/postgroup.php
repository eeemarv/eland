<?php
ob_start();
$rootpath = "../";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_passwords.php");
require_once($rootpath."includes/inc_mailfunctions.php");

session_start();
$s_id = $_SESSION["id"];
$s_name = $_SESSION["name"];
$s_letscode = $_SESSION["letscode"];
$s_accountrole = $_SESSION["accountrole"];

// Array ( [letsgroup] => LETS Test [letscode_to] => 1 [letscode_from] => 1 [amount] => 2 [minlimit] => -500 [balance] => -540 [description] => 3 )

//debug
//print_r($_POST);
if(!isset($s_id)&&$s_accountrole != "admin"){
        exit;
}

$mode = $_POST["mode"];
$id = $_POST["id"];
$posted_list = $_POST;

switch ($mode){
        case "new":
		$result = insert_group($posted_list);
                if($result == TRUE) {
			echo "<font color='green'><strong>OK</font> - Groep is opgeslagen";
                } else {
                        echo "<font color='red'><strong>Fout bij het opslaan van de groep";
                }
                break;
	case "edit":
		$result = update_group($id, $posted_list);
		if($result == TRUE) {
			echo "<font color='green'><strong>OK</font> - Groep $id aangepast";
		} else {
			echo "<font color='red'><strong>Fout bij de update van groep $id";
		}
		break;
}

///////////// FUNCTIONS //////////////////
function update_group($id, $posted_list){
    global $db;
    $result = $db->AutoExecute("letsgroups", $posted_list, 'UPDATE', "id=$id");
    return $result;
}

function insert_group($posted_list){
        global $db;
        unset($posted_list["id"]);
        $result = $db->AutoExecute("letsgroups", $posted_list, 'INSERT');
	return $result;
}

?>
